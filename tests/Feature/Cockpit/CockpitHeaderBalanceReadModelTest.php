<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitHeaderReadModelProviderContract;
use LBHurtado\XChange\Contracts\VoucherLiabilitySummaryContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Data\Money\VoucherLiabilitySummaryData;
use LBHurtado\XChange\Services\BuildBalanceOverview;
use LBHurtado\XChange\Services\Cockpit\WalletCockpitHeaderReadModelProvider;
use LBHurtado\XChange\Tests\Fakes\User;

it('binds a read-only cockpit header balance read model with safe provider fallback', function () {
    app()->forgetInstance(CockpitHeaderReadModelProviderContract::class);
    app()->instance(WalletAccessContract::class, new class implements WalletAccessContract
    {
        public function resolveForUser(mixed $user): mixed
        {
            return (object) ['id' => 123];
        }

        public function getBalance(mixed $wallet): int|float|string
        {
            return 12345;
        }

        public function assertCanAfford(mixed $wallet, int|float|string $amount): void {}

        public function debit(mixed $wallet, int|float|string $amount, array $meta = []): mixed
        {
            throw new RuntimeException('Header read model must not debit wallets.');
        }
    });

    $readModel = app(CockpitHeaderReadModelProviderContract::class)
        ->forOperator((object) ['id' => 1])
        ->toArray();

    expect($readModel)
        ->toMatchArray([
            'schema' => 'x-change.cockpit.header-read-model.v2',
            'status' => 'available',
            'authorized' => true,
            'read_only' => true,
        ])
        ->and($readModel['balances'][0]['key'])->toBe('internal')
        ->and($readModel['balances'][0]['label'])->toBe('Client Funds')
        ->and($readModel['balances'][0]['value'])->toContain('123.45')
        ->and($readModel['balances'][1]['key'])->toBe('outstanding')
        ->and($readModel['balances'][1]['value'])->toBe('Not connected')
        ->and($readModel['balances'][2]['key'])->toBe('issuance')
        ->and($readModel['balances'][2]['label'])->toBe('Issuance Capacity')
        ->and($readModel['balances'][2]['value'])->toBe('Not available')
        ->and($readModel['balances'][3]['key'])->toBe('live')
        ->and($readModel['balances'][3]['label'])->toBe('Provider Liquidity')
        ->and($readModel['balances'][3]['value'])->toBe('Not available')
        ->and($readModel['vocabulary']['internal_balance']['label'])->toBe('Client Funds')
        ->and($readModel['vocabulary']['internal_balance']['source'])->toBe('x-change')
        ->and($readModel['vocabulary']['internal_balance']['approved_for_public_display'])->toBeFalse()
        ->and($readModel['redactions']['mutates_wallets'])->toBeFalse()
        ->and($readModel['redactions']['releases_funds'])->toBeFalse()
        ->and($readModel['redactions']['calls_providers'])->toBeFalse();
});

it('caps issuance capacity by internal balance and fresh provider headroom after outstanding pay codes', function (
    int $internalBalanceMinor,
    int $providerLiquidityMinor,
    int $outstandingLiabilityMinor,
    bool $providerLiquidityIsStale,
    string $expectedValue,
) {
    config(['x-change.cockpit.header_provider_balance.enabled' => true]);

    $wallets = new class($internalBalanceMinor) implements WalletAccessContract
    {
        public function __construct(private readonly int $balanceMinor) {}

        public function resolveForUser(mixed $user): mixed
        {
            return (object) ['id' => 123];
        }

        public function getBalance(mixed $wallet): int|float|string
        {
            return $this->balanceMinor;
        }

        public function assertCanAfford(mixed $wallet, int|float|string $amount): void {}

        public function debit(mixed $wallet, int|float|string $amount, array $meta = []): mixed
        {
            throw new RuntimeException('Issuance capacity must not debit wallets.');
        }
    };
    $liabilities = new class($outstandingLiabilityMinor) implements VoucherLiabilitySummaryContract
    {
        public function __construct(private readonly int $outstandingLiabilityMinor) {}

        public function forIssuer(mixed $issuer): VoucherLiabilitySummaryData
        {
            return new VoucherLiabilitySummaryData(
                outstanding_liability_minor: $this->outstandingLiabilityMinor,
            );
        }
    };
    $fundingOverview = new class($providerLiquidityMinor, $providerLiquidityIsStale) extends BuildBalanceOverview
    {
        public function __construct(
            private readonly int $providerLiquidityMinor,
            private readonly bool $providerLiquidityIsStale,
        ) {}

        public function handle(mixed $owner, ?string $provider = null, bool $syncIfStale = true): array
        {
            expect($syncIfStale)->toBeFalse();

            return [
                'provider' => 'netbank',
                'topology' => 'ledger_pooled',
                'balances' => [
                    [
                        'key' => 'netbank_source_account',
                        'authority' => 'provider_source_account',
                        'description' => 'Cached NetBank source account liquidity.',
                        'available_balance_minor' => $this->providerLiquidityMinor,
                        'is_stale' => $this->providerLiquidityIsStale,
                    ],
                ],
            ];
        }
    };

    $readModel = (new WalletCockpitHeaderReadModelProvider(
        $wallets,
        $fundingOverview,
        $liabilities,
    ))->forOperator((object) ['id' => 1])->toArray();

    expect($readModel['balances'][2])
        ->toMatchArray([
            'key' => 'issuance',
            'label' => 'Issuance Capacity',
        ])
        ->and($readModel['balances'][2]['value'])->toContain($expectedValue);
})->with([
    'provider headroom is lower than internal balance' => [500_000, 240_000, 25_000, false, '2,150.00'],
    'internal balance is lower than provider liquidity' => [100_000, 500_000, 25_000, false, '750.00'],
    'outstanding pay codes exhaust provider liquidity' => [500_000, 240_000, 260_000, false, '0.00'],
    'stale provider liquidity fails closed' => [500_000, 240_000, 25_000, true, 'Not available'],
]);

it('can expose a read-only provider balance summary when explicitly enabled', function () {
    config(['x-change.cockpit.header_provider_balance.enabled' => true]);
    app()->forgetInstance(CockpitHeaderReadModelProviderContract::class);
    app()->instance(WalletAccessContract::class, new class implements WalletAccessContract
    {
        public function resolveForUser(mixed $user): mixed
        {
            return (object) ['id' => 789];
        }

        public function getBalance(mixed $wallet): int|float|string
        {
            return 500000;
        }

        public function assertCanAfford(mixed $wallet, int|float|string $amount): void {}

        public function debit(mixed $wallet, int|float|string $amount, array $meta = []): mixed
        {
            throw new RuntimeException('Header read model must not debit wallets.');
        }
    });
    app()->instance(BuildBalanceOverview::class, new class extends BuildBalanceOverview
    {
        public function __construct() {}

        public function handle(mixed $owner, ?string $provider = null, bool $syncIfStale = true): array
        {
            expect($syncIfStale)->toBeFalse();

            return [
                'provider' => 'netbank',
                'topology' => 'ledger_pooled',
                'authority' => 'local_ledger',
                'balances' => [
                    [
                        'key' => 'local_ledger',
                        'authority' => 'local_ledger',
                        'balance_minor' => 500000,
                        'currency' => 'PHP',
                    ],
                    [
                        'key' => 'netbank_source_account',
                        'authority' => 'provider_source_account',
                        'description' => 'NetBank source account liquidity summary.',
                        'balance_minor' => 2500000,
                        'available_balance_minor' => 2400000,
                        'currency' => 'PHP',
                        'is_stale' => false,
                    ],
                ],
            ];
        }
    });

    $readModel = app(CockpitHeaderReadModelProviderContract::class)
        ->forOperator((object) ['id' => 1])
        ->toArray();

    expect($readModel['balances'][3]['key'])->toBe('live')
        ->and($readModel['balances'][3]['label'])->toBe('NetBank Liquidity')
        ->and($readModel['balances'][3]['value'])->toContain('24,000')
        ->and($readModel['balances'][3]['helper'])->toBe('NetBank source account liquidity summary.')
        ->and($readModel['balances'][3]['tone'])->toBe('healthy')
        ->and($readModel['redactions']['calls_providers'])->toBeFalse()
        ->and($readModel['redactions']['provider_payloads_exposed'])->toBeFalse();
});

it('hydrates the cockpit dashboard with header balance read-model props', function () {
    app()->forgetInstance(CockpitHeaderReadModelProviderContract::class);
    app()->instance(WalletAccessContract::class, new class implements WalletAccessContract
    {
        public function resolveForUser(mixed $user): mixed
        {
            return (object) ['id' => 456];
        }

        public function getBalance(mixed $wallet): int|float|string
        {
            return 987650;
        }

        public function assertCanAfford(mixed $wallet, int|float|string $amount): void {}

        public function debit(mixed $wallet, int|float|string $amount, array $meta = []): mixed
        {
            throw new RuntimeException('Header read model must not debit wallets.');
        }
    });

    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard'))
        ->assertOk()
        ->assertJsonPath('props.cockpit_header_read_model.schema', 'x-change.cockpit.header-read-model.v2')
        ->assertJsonPath('props.cockpit_header_read_model.authorized', true)
        ->assertJsonPath('props.cockpit_header_read_model.read_only', true)
        ->assertJsonPath('props.cockpit_header_read_model.balances.0.key', 'internal')
        ->assertJsonPath('props.cockpit_header_read_model.balances.1.key', 'outstanding')
        ->assertJsonPath('props.cockpit_header_read_model.balances.2.key', 'issuance')
        ->assertJsonPath('props.cockpit_header_read_model.balances.3.label', 'Provider Liquidity')
        ->assertJsonPath('props.cockpit_header_read_model.balances.3.value', 'Not available')
        ->assertJsonPath('props.cockpit_header_read_model.redactions.mutates_wallets', false)
        ->assertJsonPath('props.cockpit_header_read_model.redactions.releases_funds', false)
        ->assertJsonPath('props.cockpit_header_read_model.redactions.calls_providers', false)
        ->assertJsonMissingPath('props.cockpit_header_read_model.wallet')
        ->assertJsonMissingPath('props.cockpit_header_read_model.provider_payload')
        ->assertJsonMissingPath('props.cockpit_header_read_model.raw_payload');
});

it('hydrates the cockpit dashboard with read-only voucher liability metrics', function () {
    config(['x-change.lifecycle.defaults.user_model' => User::class]);

    $operator = actingAsTestUser(100_000);
    test()->actingAs($operator);
    issueVoucher(validVoucherInstructions(25));

    $response = $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard'));

    $response->assertOk();

    $metricKeys = collect($response->json('props.dashboard_read_model.metrics'))
        ->pluck('key')
        ->all();

    expect($metricKeys)
        ->toContain('active-issued-liability')
        ->toContain('redeemed-liability')
        ->toContain('expired-liability')
        ->toContain('cancelled-liability')
        ->toContain('money-movement-model');
});
