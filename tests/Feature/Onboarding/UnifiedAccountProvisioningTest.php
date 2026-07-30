<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\Auth\StartMobileVerification;
use LBHurtado\XChange\Actions\Auth\VerifyMobileVerification;
use LBHurtado\XChange\Contracts\AccountProvisioningContract;
use LBHurtado\XChange\Contracts\TreasuryAccountPortfolioProvisioningContract;
use LBHurtado\XChange\Contracts\WalletProvisioningContract;
use LBHurtado\XChange\Data\Treasury\TreasuryAccountPortfolioData;
use LBHurtado\XChange\Services\Onboarding\DefaultAccountProvisioningService;

it('opens the local Account before provisioning its Treasury portfolio', function () {
    $user = actingAsTestUser();
    $sequence = [];

    $localAccounts = Mockery::mock(WalletProvisioningContract::class);
    $localAccounts->shouldReceive('open')
        ->once()
        ->with($user, Mockery::on(
            fn (array $input): bool => data_get($input, 'wallet.slug') === 'platform',
        ))
        ->andReturnUsing(function () use (&$sequence): object {
            $sequence[] = 'local_account';

            return (object) ['id' => 1];
        });

    $portfolios = Mockery::mock(TreasuryAccountPortfolioProvisioningContract::class);
    $portfolios->shouldReceive('provision')
        ->once()
        ->with($user)
        ->andReturnUsing(function () use (&$sequence): TreasuryAccountPortfolioData {
            $sequence[] = 'treasury_portfolio';

            return new TreasuryAccountPortfolioData(
                principalReference: 'principal:account:unified',
                positions: [],
                skippedConnections: [],
            );
        });

    $result = (new DefaultAccountProvisioningService(
        $localAccounts,
        $portfolios,
    ))->provision($user);

    expect($sequence)->toBe(['local_account', 'treasury_portfolio'])
        ->and($result->principalReference)->toBe('principal:account:unified');
});

it('provisions the same Account contract when self-registration mobile verification succeeds', function () {
    $user = actingAsTestUser();
    $user->forceFill([
        'mobile' => '639173011987',
        'mobile_verified_at' => null,
    ])->save();
    $challenge = app(StartMobileVerification::class)->handle($user);

    $accounts = Mockery::mock(AccountProvisioningContract::class);
    $accounts->shouldReceive('provision')
        ->once()
        ->with(Mockery::on(fn ($owner): bool => $owner->is($user)))
        ->andReturn(new TreasuryAccountPortfolioData(
            principalReference: 'principal:account:self-registration',
            positions: [],
            skippedConnections: [],
        ));
    app()->instance(AccountProvisioningContract::class, $accounts);

    app(VerifyMobileVerification::class)->handle($user, '000000');

    expect($challenge->refresh()->status)->toBe('verified')
        ->and($user->refresh()->mobile_verified_at)->not->toBeNull();
});

it('rolls back mobile verification when Account provisioning fails', function () {
    $user = actingAsTestUser();
    $user->forceFill([
        'mobile' => '639173011987',
        'mobile_verified_at' => null,
    ])->save();
    $challenge = app(StartMobileVerification::class)->handle($user);

    $accounts = Mockery::mock(AccountProvisioningContract::class);
    $accounts->shouldReceive('provision')
        ->once()
        ->andThrow(new RuntimeException('Treasury portfolio unavailable.'));
    app()->instance(AccountProvisioningContract::class, $accounts);

    expect(fn () => app(VerifyMobileVerification::class)->handle($user, '000000'))
        ->toThrow(RuntimeException::class, 'Treasury portfolio unavailable');

    expect($challenge->refresh()->status)->toBe('pending')
        ->and($challenge->verified_at)->toBeNull()
        ->and($user->refresh()->mobile_verified_at)->toBeNull();
});
