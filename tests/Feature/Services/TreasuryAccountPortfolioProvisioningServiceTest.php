<?php

declare(strict_types=1);

use Bavix\Wallet\Models\Transaction;
use LBHurtado\EmiCore\Contracts\ProviderReadinessProbe;
use LBHurtado\EmiCore\Contracts\SettlementProvider;
use LBHurtado\EmiCore\Data\Providers\ProviderCapabilityManifestData;
use LBHurtado\EmiCore\Data\Providers\ProviderCapabilityReadinessData;
use LBHurtado\EmiCore\Data\Providers\ProviderReadinessRequestData;
use LBHurtado\EmiCore\Enums\ProviderCapability;
use LBHurtado\EmiCore\Support\SettlementProviderRegistry;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionProvisioningContract;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\XChange\Services\Treasury\DefaultTreasuryPrincipalReferenceResolver;
use LBHurtado\XChange\Services\Treasury\TreasuryAccountPortfolioProvisioningService;
use LBHurtado\XChange\Services\Treasury\TreasuryPreflightService;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use LBHurtado\XChange\Tests\Fakes\User;

it('provisions one zero-balance client funds position for each ready provider connection', function () {
    $accountOwner = User::query()->create([
        'name' => 'Portfolio Owner',
        'email' => 'portfolio-owner@example.com',
        'password' => 'not-a-login-credential',
    ]);
    $providers = ['future_bank', 'future_emi'];
    $manifests = array_map(
        static fn (string $provider): SettlementProvider => new class($provider) implements SettlementProvider
        {
            public function __construct(private readonly string $provider) {}

            public function manifest(): ProviderCapabilityManifestData
            {
                return new ProviderCapabilityManifestData(
                    provider: $this->provider,
                    label: $this->provider,
                    capabilities: [
                        ProviderCapability::ReadinessProbe,
                        ProviderCapability::FundingEvidenceRead,
                    ],
                );
            }
        },
        $providers,
    );
    $probes = array_map(
        static fn (string $provider): ProviderReadinessProbe => new class($provider) implements ProviderReadinessProbe
        {
            public function __construct(private readonly string $provider) {}

            public function providerCode(): string
            {
                return $this->provider;
            }

            public function checkReadiness(
                ProviderReadinessRequestData $request,
            ): ProviderCapabilityReadinessData {
                return new ProviderCapabilityReadinessData(
                    provider: $request->provider,
                    connectionReference: $request->connectionReference,
                    checks: [
                        ProviderCapability::ReadinessProbe->value => true,
                        ProviderCapability::FundingEvidenceRead->value => true,
                    ],
                    issues: [],
                    checkedAt: new DateTimeImmutable,
                );
            }
        },
        $providers,
    );
    $connection = static fn (string $provider): array => [
        'provider' => $provider,
        'mode' => 'required',
        'currency' => 'PHP',
        'decimal_places' => 2,
        'settlement_resource_reference' => "resource:{$provider}:primary:php",
        'settlement_resource_type' => 'regulated_client_funds',
        'custody_mode' => 'provider_projection',
        'required_capabilities' => [
            'readiness_probe',
            'funding_evidence_read',
        ],
    ];
    $preflight = new TreasuryPreflightService(
        new TreasuryProviderConnectionCatalog([
            'future-bank-primary' => $connection('future_bank'),
            'future-emi-primary' => $connection('future_emi'),
        ]),
        new SettlementProviderRegistry($manifests),
        $probes,
    );

    config()->set('x-change.treasury.legal_profile', 'treasury-settlement-ph-v1');
    config()->set('x-change.treasury.legal_profile_version', '2026-07-24.1');

    $service = new TreasuryAccountPortfolioProvisioningService(
        $preflight,
        new DefaultTreasuryPrincipalReferenceResolver,
        app(TreasuryPositionProvisioningContract::class),
    );
    $transactionsBefore = Transaction::query()->count();

    $first = $service->provision($accountOwner);
    $second = $service->provision($accountOwner);

    expect($first->principalReference)->toStartWith('principal:account:')
        ->and($first->positions)->toHaveCount(2)
        ->and($second->positions)->toHaveCount(2)
        ->and(collect($first->positions)->pluck('provider')->all())
        ->toBe(['future_bank', 'future_emi'])
        ->and(collect($first->positions)->pluck('purpose')->unique()->all())
        ->toBe([TreasuryPositionPurpose::ClientFunds])
        ->and(collect($first->positions)->pluck('balanceMinor')->unique()->all())
        ->toBe([0])
        ->and(TreasuryPosition::query()->count())->toBe(2)
        ->and(Transaction::query()->count())->toBe($transactionsBefore);
});
