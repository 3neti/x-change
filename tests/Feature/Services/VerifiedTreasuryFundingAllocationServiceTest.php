<?php

declare(strict_types=1);

use Bavix\Wallet\Interfaces\Wallet as WalletContract;
use Bavix\Wallet\Models\Transfer;
use Bavix\Wallet\Models\Wallet;
use LBHurtado\EmiCore\Contracts\ProviderReadinessProbe;
use LBHurtado\EmiCore\Contracts\SettlementProvider;
use LBHurtado\EmiCore\Data\Providers\ProviderCapabilityManifestData;
use LBHurtado\EmiCore\Data\Providers\ProviderCapabilityReadinessData;
use LBHurtado\EmiCore\Data\Providers\ProviderReadinessRequestData;
use LBHurtado\EmiCore\Enums\ProviderCapability;
use LBHurtado\EmiCore\Support\SettlementProviderRegistry;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionProvisioningContract;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Contracts\AccountBalanceReadModelContract;
use LBHurtado\XChange\Contracts\FundingAccountCreditContract;
use LBHurtado\XChange\Services\Treasury\DefaultTreasuryPrincipalReferenceResolver;
use LBHurtado\XChange\Services\Treasury\TreasuryAccountPortfolioProvisioningService;
use LBHurtado\XChange\Services\Treasury\TreasuryPreflightService;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use LBHurtado\XChange\Services\Treasury\TreasuryProvisioningService;
use LBHurtado\XChange\Services\Treasury\VerifiedTreasuryFundingAllocationService;
use LBHurtado\XChange\Support\Funding\QrPhFundingSimulatorGuard;
use LBHurtado\XChange\Tests\Fakes\User;

it('recognizes and allocates verified funding exactly once through treasury positions', function () {
    $system = User::query()->create([
        'name' => 'System Treasury',
        'email' => 'system-allocation@example.com',
        'password' => 'not-a-login-credential',
    ]);
    $owner = User::query()->create([
        'name' => 'Account Owner',
        'email' => 'account-owner-allocation@example.com',
        'password' => 'not-a-login-credential',
    ]);
    $account = $owner->wallet()->firstOrCreate([
        'slug' => 'platform',
    ], [
        'name' => 'Legacy Platform Account',
    ]);
    $provider = new class implements SettlementProvider
    {
        public function manifest(): ProviderCapabilityManifestData
        {
            return new ProviderCapabilityManifestData(
                provider: 'future_bank',
                label: 'Future Bank',
                capabilities: [
                    ProviderCapability::ReadinessProbe,
                    ProviderCapability::BalanceRead,
                    ProviderCapability::FundingEvidenceRead,
                ],
            );
        }
    };
    $probe = new class implements ProviderReadinessProbe
    {
        public function providerCode(): string
        {
            return 'future_bank';
        }

        public function checkReadiness(
            ProviderReadinessRequestData $request,
        ): ProviderCapabilityReadinessData {
            return new ProviderCapabilityReadinessData(
                provider: $request->provider,
                connectionReference: $request->connectionReference,
                checks: [
                    ProviderCapability::ReadinessProbe->value => true,
                    ProviderCapability::BalanceRead->value => true,
                    ProviderCapability::FundingEvidenceRead->value => true,
                ],
                issues: [],
                checkedAt: new DateTimeImmutable,
            );
        }
    };
    $connections = new TreasuryProviderConnectionCatalog([
        'future-bank-primary' => [
            'provider' => 'future_bank',
            'mode' => 'required',
            'currency' => 'PHP',
            'decimal_places' => 2,
            'inventory_reference' => 'inventory:future-bank:primary:php',
            'settlement_resource_reference' => 'resource:future-bank:primary:php',
            'settlement_resource_type' => 'cash_at_bank',
            'custody_mode' => 'provider_projection',
            'required_capabilities' => [
                'readiness_probe',
                'balance_read',
                'funding_evidence_read',
            ],
        ],
    ]);
    $preflight = new TreasuryPreflightService(
        $connections,
        new SettlementProviderRegistry([$provider]),
        [$probe],
    );
    $systemResolver = new class($system) implements SystemUserResolverContract
    {
        public function __construct(private readonly User $system) {}

        public function resolve(): WalletContract
        {
            return $this->system;
        }
    };

    config()->set('x-change.treasury.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.treasury.principal_reference', 'principal:system');
    config()->set('x-change.treasury.system_mandate_reference', 'mandate:system:treasury');
    config()->set('x-change.treasury.legal_profile', 'treasury-settlement-ph-v1');
    config()->set('x-change.treasury.legal_profile_version', '2026-07-24.1');

    $positions = app(TreasuryPositionProvisioningContract::class);
    $service = new VerifiedTreasuryFundingAllocationService(
        app(FundingAccountCreditContract::class),
        $connections,
        new TreasuryProvisioningService($preflight, $systemResolver, $positions),
        new TreasuryAccountPortfolioProvisioningService(
            $preflight,
            new DefaultTreasuryPrincipalReferenceResolver,
            $positions,
        ),
        app(TreasuryPositionOperationContract::class),
        app(QrPhFundingSimulatorGuard::class),
    );

    $first = $service->allocate(
        accountReference: 'wallet:'.$account->uuid,
        provider: 'future_bank',
        amountMinor: 2_000_000_00,
        currency: 'PHP',
        evidenceReference: 'future_bank:transaction:txn-2m',
    );
    $second = $service->allocate(
        accountReference: 'wallet:'.$account->uuid,
        provider: 'future_bank',
        amountMinor: 2_000_000_00,
        currency: 'PHP',
        evidenceReference: 'future_bank:transaction:txn-2m',
    );
    $clearing = TreasuryPosition::query()
        ->where('purpose', TreasuryPositionPurpose::TreasuryClearing)
        ->sole();
    $client = TreasuryPosition::query()
        ->where('purpose', TreasuryPositionPurpose::ClientFunds)
        ->sole();
    $clearingLedger = Wallet::query()->findOrFail($clearing->internal_ledger_id);
    $clientLedger = Wallet::query()->findOrFail($client->internal_ledger_id);

    expect($second->toArray())->toBe($first->toArray())
        ->and($account->fresh()->getBalanceIntAttribute())->toBe(0)
        ->and($clearingLedger->getBalanceIntAttribute())->toBe(0)
        ->and($clientLedger->getBalanceIntAttribute())->toBe(2_000_000_00)
        ->and(app(AccountBalanceReadModelContract::class)->balanceMinor($owner, 'PHP'))
        ->toBe(2_000_000_00)
        ->and(app(AccountBalanceReadModelContract::class)->providerBalanceMinor(
            $owner,
            'future_bank',
            'PHP',
        ))->toBe(2_000_000_00)
        ->and(TreasuryPositionOperation::query()->count())->toBe(2)
        ->and(Transfer::query()->count())->toBe(1);
});
