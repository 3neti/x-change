<?php

declare(strict_types=1);

use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Models\Wallet as BavixWallet;
use Illuminate\Console\Command;
use LBHurtado\EmiCore\Contracts\ProviderBalanceReader;
use LBHurtado\EmiCore\Contracts\ProviderReadinessProbe;
use LBHurtado\EmiCore\Contracts\SettlementProvider;
use LBHurtado\EmiCore\Data\Providers\ProviderBalanceObservationData;
use LBHurtado\EmiCore\Data\Providers\ProviderBalanceRequestData;
use LBHurtado\EmiCore\Data\Providers\ProviderCapabilityManifestData;
use LBHurtado\EmiCore\Data\Providers\ProviderCapabilityReadinessData;
use LBHurtado\EmiCore\Data\Providers\ProviderReadinessRequestData;
use LBHurtado\EmiCore\Enums\ProviderCapability;
use LBHurtado\EmiCore\Support\SettlementProviderRegistry;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionOperationContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionProvisioningContract;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryPositionReadModelContract;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventoryOperation;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Enums\TreasuryOpeningBalanceStatus;
use LBHurtado\XChange\Services\Treasury\TreasuryConfigurationValidator;
use LBHurtado\XChange\Services\Treasury\TreasuryInventoryRegistrationService;
use LBHurtado\XChange\Services\Treasury\TreasuryOpeningBalanceReconciliationService;
use LBHurtado\XChange\Services\Treasury\TreasuryPreflightService;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use LBHurtado\XChange\Services\Treasury\TreasuryProvisioningService;
use LBHurtado\XChange\Tests\Fakes\User;

it('recognizes an authoritative opening balance once into inventory and unattributed position', function () {
    [$service] = openingBalanceReconciliationService(1_000_000_00);

    $first = $service->reconcile(['future-primary']);
    $second = $service->reconcile(['future-primary']);
    $unattributed = TreasuryPosition::query()
        ->where('purpose', TreasuryPositionPurpose::LegacyUnattributed)
        ->sole();
    $unattributedLedger = BavixWallet::query()->findOrFail(
        $unattributed->internal_ledger_id,
    );
    $clearing = TreasuryPosition::query()
        ->where('purpose', TreasuryPositionPurpose::TreasuryClearing)
        ->sole();
    $clearingLedger = BavixWallet::query()->findOrFail($clearing->internal_ledger_id);

    expect($first->passes())->toBeTrue()
        ->and($first->connections)->toHaveCount(1)
        ->and($first->connections[0]->status)->toBe(TreasuryOpeningBalanceStatus::Recognized)
        ->and($first->connections[0]->differenceMinor)->toBe(1_000_000_00)
        ->and($second->connections[0]->status)->toBe(TreasuryOpeningBalanceStatus::Reconciled)
        ->and($second->connections[0]->differenceMinor)->toBe(0)
        ->and(TreasuryInventory::query()->sole()->balance_minor)->toBe(1_000_000_00)
        ->and($unattributedLedger->getBalanceIntAttribute())->toBe(1_000_000_00)
        ->and($clearingLedger->getBalanceIntAttribute())->toBe(0)
        ->and(TreasuryInventoryOperation::query()->count())->toBe(1)
        ->and(TreasuryPositionOperation::query()->count())->toBe(1);
});

it('fails closed without debiting when the provider is below internal attribution', function () {
    [$service, $reader] = openingBalanceReconciliationService(1_000_000_00);
    $service->reconcile(['future-primary']);
    $reader->amountMinor = 900_000_00;
    $reader->evidenceReference = 'future-balance:lower';

    $result = $service->reconcile(['future-primary']);

    expect($result->passes())->toBeFalse()
        ->and($result->connections[0]->status)
        ->toBe(TreasuryOpeningBalanceStatus::ReviewRequired)
        ->and($result->connections[0]->reason)
        ->toBe('provider-balance-below-internal-attribution')
        ->and($result->connections[0]->differenceMinor)->toBe(-100_000_00)
        ->and(TreasuryInventory::query()->sole()->balance_minor)->toBe(1_000_000_00)
        ->and(TreasuryInventoryOperation::query()->count())->toBe(1)
        ->and(TreasuryPositionOperation::query()->count())->toBe(1);
});

it('exposes opening reconciliation as an idempotent package command', function () {
    [$service] = openingBalanceReconciliationService(250_000_00);
    app()->instance(TreasuryOpeningBalanceReconciliationService::class, $service);

    $this->artisan('x-change:treasury:reconcile-opening', [
        '--connection' => ['future-primary'],
        '--json' => true,
    ])
        ->expectsOutputToContain('"status":"recognized"')
        ->assertExitCode(Command::SUCCESS);

    $this->artisan('x-change:treasury:reconcile-opening', [
        '--connection' => ['future-primary'],
        '--json' => true,
    ])
        ->expectsOutputToContain('"status":"reconciled"')
        ->assertExitCode(Command::SUCCESS);
});

it('simulates replay-safe provider deposits only through the opening reconciliation pipeline', function () {
    enableNetbankTreasuryForTests();
    config()->set('x-change.treasury.simulator.enabled', true);

    $first = app(TreasuryOpeningBalanceReconciliationService::class)
        ->simulateDeposit('netbank-primary', 1_000_000_00, 'SIM-DEPOSIT-1');
    $replay = app(TreasuryOpeningBalanceReconciliationService::class)
        ->simulateDeposit('netbank-primary', 1_000_000_00, 'SIM-DEPOSIT-1');
    $second = app(TreasuryOpeningBalanceReconciliationService::class)
        ->simulateDeposit('netbank-primary', 500_000_00, 'SIM-DEPOSIT-2');

    expect($first->status)->toBe(TreasuryOpeningBalanceStatus::Recognized)
        ->and($first->differenceMinor)->toBe(1_000_000_00)
        ->and($replay->status)->toBe(TreasuryOpeningBalanceStatus::Reconciled)
        ->and($replay->positionBalanceMinor)->toBe(1_000_000_00)
        ->and($second->status)->toBe(TreasuryOpeningBalanceStatus::Recognized)
        ->and($second->positionBalanceMinor)->toBe(1_500_000_00)
        ->and(TreasuryInventory::query()->sole()->balance_minor)->toBe(1_500_000_00)
        ->and(TreasuryInventoryOperation::query()->count())->toBe(2)
        ->and(TreasuryPositionOperation::query()->count())->toBe(2);
});

it('guards the local provider deposit simulation command', function () {
    enableNetbankTreasuryForTests();
    config()->set('x-change.treasury.simulator.enabled', true);

    $this->artisan('x-change:treasury:simulate-deposit', [
        'connection' => 'netbank-primary',
        'amount' => '100000000',
        '--reference' => 'SIMULATED-MILLION-PESOS',
    ])->assertExitCode(Command::FAILURE);

    $this->artisan('x-change:treasury:simulate-deposit', [
        'connection' => 'netbank-primary',
        'amount' => '100000000',
        '--reference' => 'SIMULATED-MILLION-PESOS',
        '--commit' => true,
        '--json' => true,
    ])
        ->expectsOutputToContain('"status":"recognized"')
        ->assertExitCode(Command::SUCCESS);
});

/**
 * @return array{
 *     TreasuryOpeningBalanceReconciliationService,
 *     ProviderBalanceReader&object
 * }
 */
function openingBalanceReconciliationService(
    int $amountMinor,
): array {
    $systemPrincipal = User::query()->create([
        'name' => 'System Treasury Principal',
        'email' => 'opening-system+'.fake()->uuid().'@example.com',
        'password' => 'not-a-login-credential',
    ]);
    $provider = new class implements SettlementProvider
    {
        public function manifest(): ProviderCapabilityManifestData
        {
            return new ProviderCapabilityManifestData(
                provider: 'future_emi',
                label: 'Future EMI',
                capabilities: [
                    ProviderCapability::ReadinessProbe,
                    ProviderCapability::BalanceRead,
                ],
            );
        }
    };
    $probe = new class implements ProviderReadinessProbe
    {
        public function providerCode(): string
        {
            return 'future_emi';
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
                ],
                issues: [],
                checkedAt: new DateTimeImmutable('2026-07-24T10:00:00+08:00'),
            );
        }
    };
    $reader = new class($amountMinor) implements ProviderBalanceReader
    {
        public string $evidenceReference = 'future-balance:opening';

        public function __construct(public int $amountMinor) {}

        public function providerCode(): string
        {
            return 'future_emi';
        }

        public function readBalance(
            ProviderBalanceRequestData $request,
        ): ProviderBalanceObservationData {
            return new ProviderBalanceObservationData(
                provider: 'future_emi',
                connectionReference: $request->connectionReference,
                settlementResourceReference: $request->settlementResourceReference,
                amountMinor: $this->amountMinor,
                currency: $request->currency,
                observedAt: new DateTimeImmutable('2026-07-24T10:00:00+08:00'),
                evidenceReference: $this->evidenceReference,
            );
        }
    };
    $systemResolver = new class($systemPrincipal) implements SystemUserResolverContract
    {
        public function __construct(private readonly User $systemPrincipal) {}

        public function resolve(): Wallet
        {
            return $this->systemPrincipal;
        }
    };
    $catalog = new TreasuryProviderConnectionCatalog([
        'future-primary' => [
            'provider' => 'future_emi',
            'mode' => 'required',
            'currency' => 'PHP',
            'decimal_places' => 2,
            'inventory_reference' => 'inventory:future_emi:primary:php',
            'settlement_resource_reference' => 'resource:future_emi:primary:php',
            'settlement_resource_type' => 'regulated_stored_value',
            'custody_mode' => 'provider_projection',
            'required_capabilities' => [
                'readiness_probe',
                'balance_read',
            ],
        ],
    ]);
    $preflight = new TreasuryPreflightService(
        $catalog,
        new SettlementProviderRegistry([$provider]),
        [$probe],
    );
    config()->set('x-change.treasury.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.treasury.principal_reference', 'principal:system');
    config()->set('x-change.treasury.system_mandate_reference', 'mandate:system:treasury');
    config()->set('x-change.treasury.legal_profile', 'treasury-settlement-ph-v1');
    config()->set('x-change.treasury.legal_profile_version', '2026-07-24.1');
    $provisioning = new TreasuryProvisioningService(
        $preflight,
        new TreasuryConfigurationValidator($catalog),
        $systemResolver,
        app(TreasuryPositionProvisioningContract::class),
    );
    $service = new TreasuryOpeningBalanceReconciliationService(
        $preflight,
        $provisioning,
        app(TreasuryInventoryOperationContract::class),
        app(TreasuryInventoryRegistrationService::class),
        app(TreasuryInventoryPositionReadModelContract::class),
        app(TreasuryPositionOperationContract::class),
        app(TreasuryPositionReadModelContract::class),
        [$reader],
    );

    return [$service, $reader];
}
