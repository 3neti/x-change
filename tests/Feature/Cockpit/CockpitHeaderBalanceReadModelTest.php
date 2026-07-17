<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitHeaderReadModelProviderContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;

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
