<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitHeaderReadModelProviderContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Services\BuildBalanceOverview;

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
            'schema' => 'x-change.cockpit.header-read-model.v1',
            'status' => 'available',
            'authorized' => true,
            'read_only' => true,
        ])
        ->and($readModel['balances'][0]['key'])->toBe('internal')
        ->and($readModel['balances'][0]['value'])->toContain('123.45')
        ->and($readModel['balances'][1]['key'])->toBe('live')
        ->and($readModel['balances'][1]['value'])->toBe('Provider balance not connected')
        ->and($readModel['redactions']['mutates_wallets'])->toBeFalse()
        ->and($readModel['redactions']['calls_providers'])->toBeFalse();
});

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

    expect($readModel['balances'][1]['key'])->toBe('live')
        ->and($readModel['balances'][1]['value'])->toContain('25,000')
        ->and($readModel['balances'][1]['helper'])->toBe('NetBank source account liquidity summary.')
        ->and($readModel['balances'][1]['tone'])->toBe('healthy')
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
        ->assertJsonPath('props.cockpit_header_read_model.schema', 'x-change.cockpit.header-read-model.v1')
        ->assertJsonPath('props.cockpit_header_read_model.authorized', true)
        ->assertJsonPath('props.cockpit_header_read_model.read_only', true)
        ->assertJsonPath('props.cockpit_header_read_model.balances.0.key', 'internal')
        ->assertJsonPath('props.cockpit_header_read_model.balances.1.value', 'Provider balance not connected')
        ->assertJsonPath('props.cockpit_header_read_model.redactions.mutates_wallets', false)
        ->assertJsonPath('props.cockpit_header_read_model.redactions.calls_providers', false)
        ->assertJsonMissingPath('props.cockpit_header_read_model.wallet')
        ->assertJsonMissingPath('props.cockpit_header_read_model.provider_payload')
        ->assertJsonMissingPath('props.cockpit_header_read_model.raw_payload');
});
