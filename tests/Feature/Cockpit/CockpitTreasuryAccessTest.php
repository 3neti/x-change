<?php

declare(strict_types=1);

use Bavix\Wallet\Interfaces\Wallet;
use Illuminate\Auth\Access\AuthorizationException;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XChange\Contracts\CockpitTreasuryAccessContract;
use LBHurtado\XChange\Tests\Fakes\User;

it('binds Treasury Cockpit access to the resolved system principal', function () {
    $system = enableNetbankTreasuryForTests();
    $accountHolder = actingAsTestUser(0);
    $access = app(CockpitTreasuryAccessContract::class);

    expect($access)
        ->canViewTreasuryControls($system)->toBeTrue()
        ->canRefreshProviderLiquidity($system)->toBeTrue()
        ->canManageTreasuryReconciliation($system)->toBeTrue()
        ->canViewTreasuryControls($accountHolder)->toBeFalse()
        ->canRefreshProviderLiquidity($accountHolder)->toBeFalse()
        ->canManageTreasuryReconciliation($accountHolder)->toBeFalse();
});

it('compares the complete principal identity instead of an identifier alone', function () {
    $system = enableNetbankTreasuryForTests();
    $lookalike = new class extends User {};
    $lookalike->forceFill([
        'id' => $system->getKey(),
        'name' => 'Lookalike Account',
        'email' => 'lookalike@example.test',
        'password' => 'not-a-login-credential',
    ]);

    expect(app(CockpitTreasuryAccessContract::class))
        ->canViewTreasuryControls($lookalike)
        ->toBeFalse();
});

it('fails closed when the system principal cannot be resolved', function () {
    app()->instance(
        SystemUserResolverContract::class,
        new class implements SystemUserResolverContract
        {
            public function resolve(): Wallet
            {
                throw new RuntimeException('Ambiguous system principal.');
            }
        },
    );
    app()->forgetInstance(CockpitTreasuryAccessContract::class);

    $accountHolder = actingAsTestUser(0);
    $access = app(CockpitTreasuryAccessContract::class);

    expect($access)
        ->canViewTreasuryControls($accountHolder)->toBeFalse()
        ->canRefreshProviderLiquidity($accountHolder)->toBeFalse()
        ->canManageTreasuryReconciliation($accountHolder)->toBeFalse();

    expect(fn () => $access->authorizeProviderLiquidityRefresh($accountHolder))
        ->toThrow(
            AuthorizationException::class,
            'Provider liquidity controls are restricted to System Treasury.',
        );

    expect(fn () => $access->authorizeTreasuryReconciliation($accountHolder))
        ->toThrow(
            AuthorizationException::class,
            'Treasury reconciliation controls are restricted to System Treasury.',
        );
});
