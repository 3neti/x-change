<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use LBHurtado\EmiCore\Contracts\ProviderBalanceReader;
use LBHurtado\EmiCore\Data\Providers\ProviderBalanceObservationData;
use LBHurtado\EmiCore\Data\Providers\ProviderBalanceRequestData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\VerifiedTreasuryFundingAllocationContract;
use LBHurtado\XChange\Lifecycle\Runners\TreasuryLiveBasicCashScenarioRunner;
use LBHurtado\XChange\Models\LifecycleMoneyRun;
use LBHurtado\XChange\Services\Treasury\TreasuryLifecycleAccountingSnapshot;
use LBHurtado\XChange\Services\Treasury\TreasuryOpeningBalanceReconciliationService;
use LBHurtado\XChange\Services\Treasury\TreasuryPreflightService;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use LBHurtado\XChange\Services\Treasury\TreasuryProvisioningService;
use LBHurtado\XChange\Tests\Fakes\User as FakeLifecycleUser;

beforeEach(function () {
    config([
        'x-change.lifecycle.defaults.user_model' => FakeLifecycleUser::class,
        'x-change.lifecycle.defaults.system_user_email' => 'system@example.test',
        'x-change.lifecycle.defaults.test_user_email' => 'lester@hurtado.ph',
        'x-change.lifecycle.defaults.test_user_mobile' => '09173011987',
        'x-change.lifecycle.treasury_live_basic_cash.enabled' => true,
        'x-change.lifecycle.treasury_live_basic_cash.allowed_environments' => [
            'testing',
        ],
        'x-change.provider_runtime.lifecycle.allow_live_provider_scenarios' => true,
        'x-change.settlement.default_driver' => 'philhealth-bst',
        'x-change.settlement.drivers_path' => settlementEnvelopeDriversPath(),
        'queue.default' => 'sync',
    ]);

    Artisan::call('xchange:lifecycle:prepare', [
        '--seed' => true,
    ]);
});

it('fails closed with a concise scenario-specific response before provider access', function () {
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'treasury_live_basic_cash',
        '--json' => true,
    ]);
    $payload = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(Command::FAILURE)
        ->and($payload['scenario'])->toBe('treasury_live_basic_cash')
        ->and($payload['mode'])->toBe('treasury_live_basic_cash')
        ->and($payload['message'])->toContain('--live-provider')
        ->and($payload)->not->toHaveKey('integrations')
        ->and(LifecycleMoneyRun::query()->count())->toBe(0);
});

it('posts provider principal separately from the sender system charge and never repeats its transfer', function () {
    $reader = configureLiveNetbankAccounting([1_000_000_00, 999_987_50]);
    $provider = fakePayoutProvider()->willReturnSuccessfulResult(
        transactionId: 'TXN-TREASURY-LIVE-1',
        uuid: 'uuid-treasury-live-1',
        provider: 'netbank',
    );
    $issuer = FakeLifecycleUser::query()
        ->where('email', 'lester@hurtado.ph')
        ->sole();
    $arguments = [
        'scenario' => 'treasury_live_basic_cash',
        '--issuer' => (string) $issuer->getKey(),
        '--live-provider' => true,
        '--confirm-live-transfer' => true,
        '--run-reference' => 'treasury-live-basic-cash-test-001',
        '--json' => true,
    ];

    $firstExitCode = Artisan::call('xchange:lifecycle:run', $arguments);
    $first = LifecycleMoneyRun::query()->sole()->result_summary;
    $secondExitCode = Artisan::call('xchange:lifecycle:run', $arguments);
    $second = json_decode(Artisan::output(), true);
    $netbankBefore = collect(data_get(
        $first,
        'accounting.before_issuance.connections',
        [],
    ))->firstWhere('reference', 'netbank-primary');
    $paynamicsBefore = collect(data_get(
        $first,
        'accounting.before_issuance.connections',
        [],
    ))->firstWhere('reference', 'paynamics-primary');
    $encoded = json_encode($first, JSON_THROW_ON_ERROR);
    expect($firstExitCode)->toBe(Command::SUCCESS)
        ->and($first['schema'])->toBe('x-change.lifecycle.treasury-live-basic-cash.v1')
        ->and($first['provider_transfer_succeeded'])->toBeTrue()
        ->and($first['accounting_status'])->toBe('reconciled')
        ->and(data_get($first, 'pay_code.issued'))->toBeTrue()
        ->and(data_get($first, 'pay_code.claimed'))->toBeTrue()
        ->and(data_get($first, 'execution.reconciliation.provider_transaction_id'))
        ->toBe('TXN-TREASURY-LIVE-1')
        ->and(data_get($netbankBefore, 'provider_observation.balance_minor'))
        ->toBe(1_000_000_00)
        ->and(data_get($netbankBefore, 'inventory.balance_minor'))
        ->toBe(1_000_000_00)
        ->and(data_get(
            $netbankBefore,
            'system_positions.by_purpose.legacy_unattributed',
        ))->toBe(999_900_00)
        ->and(data_get($netbankBefore, 'account_positions.status'))
        ->toBe('provisioned')
        ->and(data_get($netbankBefore, 'account_positions.balance_minor'))->toBe(10_000)
        ->and(data_get(
            $netbankBefore,
            'account_positions.by_purpose.client_funds',
        ))->toBe(10_000)
        ->and(data_get(
            $netbankBefore,
            'account_positions.by_purpose.pay_code_reserve',
        ))->toBe(0)
        ->and(data_get($paynamicsBefore, 'active'))->toBeFalse()
        ->and(data_get($paynamicsBefore, 'account_positions.balance_minor'))->toBeNull()
        ->and(data_get(
            $first,
            'accounting.after_issuance.account.liability.outstanding_liability_minor',
        ))->toBe(1250)
        ->and(data_get(
            $first,
            'accounting.after_claim.account.liability.outstanding_liability_minor',
        ))->toBe(0)
        ->and(data_get(
            $first,
            'accounting.after_issuance.connections.0.account_positions.by_purpose.client_funds',
        ))->toBe(8_750)
        ->and(data_get(
            $first,
            'accounting.after_issuance.connections.0.account_positions.by_purpose.pay_code_reserve',
        ))->toBe(1_250)
        ->and(data_get(
            $first,
            'accounting.after_claim.connections.0.account_positions.by_purpose.client_funds',
        ))->toBe(8_750)
        ->and(data_get(
            $first,
            'accounting.after_claim.connections.0.account_positions.by_purpose.pay_code_reserve',
        ))->toBe(0)
        ->and(data_get(
            $first,
            'accounting.after_claim.connections.0.inventory.balance_minor',
        ))->toBe(999_987_50)
        ->and(data_get(
            $first,
            'treasury_settlement.beneficiary_amount_minor',
        ))->toBe(1_250)
        ->and(data_get(
            $first,
            'treasury_settlement.provider_inventory_outflow_minor',
        ))->toBe(1_250)
        ->and(data_get(
            $first,
            'treasury_settlement.configured_rail_fee_minor',
        ))->toBe(1_000)
        ->and(data_get(
            $first,
            'treasury_settlement.sender_system_charge_minor',
        ))->toBe(1_500)
        ->and(data_get(
            $first,
            'treasury_settlement.sender_system_charge_status',
        ))->toBe('legacy_compatibility_ledger')
        ->and(data_get(
            $first,
            'accounting_boundary.outbound_treasury_posting',
        ))->toBe('provider_principal_only')
        ->and(data_get(
            $first,
            'accounting_boundary.sender_system_charge',
        ))->toBe('legacy_compatibility_ledger')
        ->and($encoded)->not->toContain('09173011987')
        ->and($encoded)->not->toContain('raw_request')
        ->and($encoded)->not->toContain('raw_response')
        ->and($secondExitCode)->toBe(Command::SUCCESS)
        ->and(data_get($second, 'idempotency.replayed'))->toBeTrue()
        ->and(data_get($second, 'idempotency.provider_transfer_repeated'))->toBeFalse()
        ->and(LifecycleMoneyRun::query()->count())->toBe(1)
        ->and(Voucher::query()->whereKey(data_get($first, 'pay_code.id'))->count())->toBe(1)
        ->and($reader->callCount)->toBe(2);

    $provider->assertDisburseCalledTimes(1);
});

it('rechecks a lagging provider balance without repeating its transfer', function () {
    $reader = configureLiveNetbankAccounting([
        1_000_000_00,
        1_000_000_00,
        999_987_50,
    ]);
    $provider = fakePayoutProvider()->willReturnSuccessfulResult(
        transactionId: 'TXN-TREASURY-LIVE-REVIEW',
        uuid: 'uuid-treasury-live-review',
        provider: 'netbank',
    );
    $issuer = FakeLifecycleUser::query()
        ->where('email', 'lester@hurtado.ph')
        ->sole();
    $arguments = [
        'scenario' => 'treasury_live_basic_cash',
        '--issuer' => (string) $issuer->getKey(),
        '--live-provider' => true,
        '--confirm-live-transfer' => true,
        '--run-reference' => 'treasury-live-basic-cash-review-001',
        '--json' => true,
    ];

    $firstExitCode = Artisan::call('xchange:lifecycle:run', $arguments);
    $first = LifecycleMoneyRun::query()->sole()->result_summary;
    $secondExitCode = Artisan::call('xchange:lifecycle:run', $arguments);
    $second = json_decode(Artisan::output(), true);

    expect($firstExitCode)->toBe(Command::FAILURE)
        ->and($first['provider_transfer_succeeded'])->toBeTrue()
        ->and($first['accounting_status'])->toBe('provider_sync_pending')
        ->and(data_get(
            $first,
            'accounting.after_claim.connections.0.provider_observation.balance_minor',
        ))->toBe(1_000_000_00)
        ->and(data_get(
            $first,
            'accounting.after_claim.connections.0.provider_observation.reason',
        ))->toBe('provider-balance-update-pending')
        ->and($secondExitCode)->toBe(Command::SUCCESS)
        ->and($second['accounting_status'])->toBe('reconciled')
        ->and(data_get($second, 'idempotency.replayed'))->toBeTrue()
        ->and(data_get($second, 'idempotency.provider_transfer_repeated'))->toBeFalse()
        ->and($reader->callCount)->toBe(3);

    $provider->assertDisburseCalledTimes(1);
});

/**
 * @param  list<int>  $balances
 * @return ProviderBalanceReader&object{callCount: int}
 */
function configureLiveNetbankAccounting(array $balances): object
{
    enableNetbankTreasuryForTests();
    config()->set('x-change.treasury.connections.paynamics-primary', [
        'provider' => 'paynamics_constellation',
        'mode' => 'disabled',
        'currency' => 'PHP',
        'decimal_places' => 2,
        'inventory_reference' => 'inventory:paynamics:wallet-float',
        'settlement_resource_reference' => 'resource:paynamics:corporate-wallet',
        'settlement_resource_type' => 'emi_wallet_float',
        'custody_mode' => 'provider_projection',
        'required_capabilities' => [],
    ]);
    $reader = new class($balances) implements ProviderBalanceReader
    {
        public int $callCount = 0;

        /**
         * @param  list<int>  $balances
         */
        public function __construct(private readonly array $balances) {}

        public function providerCode(): string
        {
            return 'netbank';
        }

        public function readBalance(
            ProviderBalanceRequestData $request,
        ): ProviderBalanceObservationData {
            $index = min($this->callCount, count($this->balances) - 1);
            $amountMinor = $this->balances[$index];
            $this->callCount++;

            return new ProviderBalanceObservationData(
                provider: 'netbank',
                connectionReference: $request->connectionReference,
                settlementResourceReference: $request->settlementResourceReference,
                amountMinor: $amountMinor,
                currency: $request->currency,
                observedAt: new DateTimeImmutable(
                    '2026-07-24T21:00:00+08:00',
                ),
                evidenceReference: 'netbank-balance:test-'.$this->callCount,
            );
        }
    };

    app()->instance($reader::class, $reader);
    app()->tag($reader::class, 'emi.provider-balance-readers');

    foreach ([
        TreasuryProviderConnectionCatalog::class,
        TreasuryPreflightService::class,
        TreasuryProvisioningService::class,
        TreasuryOpeningBalanceReconciliationService::class,
        TreasuryAccountPortfolioProvisioningContract::class,
        TreasuryLifecycleAccountingSnapshot::class,
        TreasuryLiveBasicCashScenarioRunner::class,
    ] as $abstract) {
        app()->forgetInstance($abstract);
    }

    $issuer = FakeLifecycleUser::query()
        ->where('email', 'lester@hurtado.ph')
        ->sole();
    $account = $issuer->wallet()->where('slug', 'platform')->sole();
    $inventoryOperations = app(TreasuryInventoryOperationContract::class);
    $inventoryOperations->registerInventory(new TreasuryInventoryData(
        inventoryReference: 'inventory:netbank:vca-cash',
        resourceType: 'cash_at_bank',
        currency: 'PHP',
        capacityMinor: 0,
        status: 'requested',
        idempotencyKey: 'register:inventory:netbank:vca-cash',
        externalReference: 'resource:netbank:corporate-vca',
    ));
    $inventoryOperations->recognize(
        new TreasuryInventoryRecognitionData(
            operationReference: 'funding-recognition:netbank:test-funded-issuer',
            inventoryReference: 'inventory:netbank:vca-cash',
            settlementResourceReference: 'resource:netbank:corporate-vca',
            amountMinor: 10_000,
            currency: 'PHP',
            status: 'requested',
            idempotencyKey: 'funding-recognition-key:netbank:test-funded-issuer',
            externalReference: 'netbank:test-funded-issuer',
        ),
    );
    app(VerifiedTreasuryFundingAllocationContract::class)->allocate(
        accountReference: 'wallet:'.$account->uuid,
        provider: 'netbank',
        amountMinor: 10_000,
        currency: 'PHP',
        evidenceReference: 'netbank:test-funded-issuer',
        metadata: ['source' => 'treasury_live_basic_cash_test_fixture'],
    );

    return $reader;
}
