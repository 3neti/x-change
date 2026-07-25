<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\ProviderBalanceSnapshot;
use LBHurtado\XChange\Services\CheckNetbankSourceAccountReadiness;
use LBHurtado\XChange\Services\ProviderBalanceSnapshotStore;

beforeEach(function () {
    config([
        'x-change.provider_runtime.default_provider' => 'netbank',
        'x-change.provider_runtime.providers.netbank.source_account_readiness.enabled' => true,
        'x-change.provider_runtime.providers.netbank.source_account_readiness.account_number' => '113001000019',
        'x-change.funding.liquidity_refresh.middleware' => [],
    ]);
});

it('refreshes provider liquidity without accepting financial facts or posting money', function () {
    enableNetbankTreasuryForTests();
    $operator = actingAsTestUser(0);
    $audit = fakeAuditLogger()->reset();
    $positionCount = TreasuryPosition::query()->count();
    $inventoryCount = TreasuryInventory::query()->count();

    $readiness = Mockery::mock(CheckNetbankSourceAccountReadiness::class);
    $readiness->shouldReceive('handle')
        ->once()
        ->with()
        ->andReturn([
            'enabled' => true,
            'ready' => true,
            'checked' => true,
            'account_number_masked' => '********0019',
            'balance_minor' => 125_000,
            'available_balance_minor' => 124_852,
            'currency' => 'PHP',
            'as_of' => now()->subSecond()->toIso8601String(),
            'fetched_at' => now()->toIso8601String(),
            'message' => 'NetBank source account balance was refreshed.',
        ]);
    app()->instance(CheckNetbankSourceAccountReadiness::class, $readiness);

    $this->post(route('x-change.cockpit.funding.liquidity-refreshes.store'), [
        'amount_minor' => 999_999_999,
        'account_number' => 'forged-account',
        'provider' => 'forged-provider',
    ])->assertRedirect(route('x-change.cockpit.funding.index'))
        ->assertSessionHas(
            'funding_notice',
            'Provider liquidity refreshed. Issuance Capacity was recalculated.',
        );

    $snapshot = ProviderBalanceSnapshot::query()
        ->where('provider_code', 'netbank')
        ->where('balance_key', 'netbank_source_account')
        ->sole();

    expect($snapshot)
        ->available_balance_minor->toBe(124_852)
        ->currency->toBe('PHP')
        ->account_reference_masked->toBe('********0019')
        ->refresh_status->toBe('fresh')
        ->and(FundingIntent::query()->count())->toBe(0)
        ->and(TreasuryPosition::query()->count())->toBe($positionCount)
        ->and(TreasuryInventory::query()->count())->toBe($inventoryCount)
        ->and($audit->last())->toMatchArray([
            'event' => 'funding.liquidity.refresh_completed',
            'context' => [
                'operator_type' => $operator::class,
                'operator_id' => (string) $operator->getAuthIdentifier(),
                'provider' => 'netbank',
                'connection_reference' => 'netbank-primary',
                'outcome' => 'refreshed',
                'financial_posting' => false,
            ],
        ]);
});

it('retains the last good snapshot and returns a sanitized failure', function () {
    enableNetbankTreasuryForTests();
    actingAsTestUser(0);
    app(ProviderBalanceSnapshotStore::class)->recordSuccess(
        'netbank',
        'netbank_source_account',
        [
            'balance_minor' => 125_000,
            'available_balance_minor' => 124_852,
            'currency' => 'PHP',
            'fetched_at' => now()->subHour(),
        ],
    );

    $readiness = Mockery::mock(CheckNetbankSourceAccountReadiness::class);
    $readiness->shouldReceive('handle')
        ->once()
        ->with()
        ->andReturn([
            'enabled' => true,
            'ready' => false,
            'checked' => true,
            'message' => 'SECRET provider response and account 113001000019',
        ]);
    app()->instance(CheckNetbankSourceAccountReadiness::class, $readiness);

    $this->post(route(
        'x-change.cockpit.funding.liquidity-refreshes.store',
    ))->assertRedirect(route('x-change.cockpit.funding.index'))
        ->assertSessionHasErrors([
            'liquidity_refresh' => 'Provider liquidity could not be refreshed. The last observation was retained.',
        ]);

    $snapshot = ProviderBalanceSnapshot::query()->sole();

    expect($snapshot)
        ->available_balance_minor->toBe(124_852)
        ->refresh_status->toBe('refresh_failed')
        ->failure_reason->toBe('NetBank source account refresh failed.')
        ->and(json_encode($snapshot->toArray(), JSON_THROW_ON_ERROR))
        ->not->toContain('SECRET')
        ->not->toContain('113001000019');
});

it('requires an authenticated Cockpit operator', function () {
    $this->postJson(route(
        'x-change.cockpit.funding.liquidity-refreshes.store',
    ))->assertUnauthorized();
});

it('does not overlap refreshes for the same Treasury connection', function () {
    enableNetbankTreasuryForTests();
    actingAsTestUser(0);

    $readiness = Mockery::mock(CheckNetbankSourceAccountReadiness::class);
    $readiness->shouldNotReceive('handle');
    app()->instance(CheckNetbankSourceAccountReadiness::class, $readiness);

    $lock = Cache::lock(
        'x-change:funding-liquidity-refresh:'.hash('sha256', 'netbank-primary'),
        30,
    );

    expect($lock->get())->toBeTrue();

    try {
        $this->post(route(
            'x-change.cockpit.funding.liquidity-refreshes.store',
        ))->assertRedirect(route('x-change.cockpit.funding.index'))
            ->assertSessionHasErrors([
                'liquidity_refresh' => 'Liquidity is already being refreshed. Try again shortly.',
            ]);
    } finally {
        $lock->release();
    }
});
