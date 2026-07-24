<?php

declare(strict_types=1);

use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\XChange\Providers\XChangeServiceProvider;
use LBHurtado\XChange\Tests\Fakes\User;

beforeEach(function () {
    config()->set('x-change.onboarding.issuer_model', User::class);
    config()->set('x-change.payout.system_user_id', null);
    config()->set('x-change.payout.system_user_column', 'id');

    $method = new ReflectionMethod(XChangeServiceProvider::class, 'alignAccountSystemUser');
    $method->setAccessible(true);
    $method->invoke(new XChangeServiceProvider(app()));
});

it('contributes the x-change system-principal candidate from cached configuration', function () {
    expect(config('account.system_user.candidates.x-change'))->toBe([
        'model' => User::class,
        'identifier' => config('x-change.payout.system_user_id'),
        'identifier_column' => config('x-change.payout.system_user_column'),
    ]);
});

it('resolves the x-change system principal through the wallet contract', function () {
    $systemPrincipal = User::query()->create([
        'name' => 'Treasury Principal',
        'email' => 'treasury-principal@example.com',
        'password' => 'not-a-login-credential',
    ]);

    config()->set('x-change.payout.system_user_id', $systemPrincipal->getKey());

    $method = new ReflectionMethod(XChangeServiceProvider::class, 'alignAccountSystemUser');
    $method->setAccessible(true);
    $method->invoke(new XChangeServiceProvider(app()));

    $resolved = app(SystemUserResolverContract::class)->resolve();

    expect($resolved->is($systemPrincipal))->toBeTrue();
});

it('keeps the legacy wallet configuration aligned for compatibility', function () {
    $candidate = config('account.system_user.candidates.x-change');

    expect(config('account.system_user.model'))->toBe($candidate['model'])
        ->and(config('account.system_user.identifier'))->toBe($candidate['identifier'])
        ->and(config('account.system_user.identifier_column'))->toBe($candidate['identifier_column']);
});
