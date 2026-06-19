<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\XChange\Services\PayoutProviderResolver;
use LBHurtado\XChange\Tests\Fakes\FakePayoutProvider;

it('resolves a payout provider alias from the emi registry', function () {
    config()->set('emi.payout_providers.netbank', FakePayoutProvider::class);
    config()->set('emi.payout_providers.paynamics', FakePayoutProvider::class);

    $resolver = app(PayoutProviderResolver::class);

    expect($resolver->resolve('netbank'))->toBe(FakePayoutProvider::class)
        ->and($resolver->resolve('NetBank'))->toBe(FakePayoutProvider::class)
        ->and($resolver->resolve('paynamics'))->toBe(FakePayoutProvider::class);
});

it('keeps full payout provider class names compatible', function () {
    expect(app(PayoutProviderResolver::class)->resolve(FakePayoutProvider::class))
        ->toBe(FakePayoutProvider::class);
});

it('fails with a useful message for an unknown payout provider alias', function () {
    config()->set('emi.payout_providers.netbank', FakePayoutProvider::class);
    config()->set('emi.payout_providers.paynamics', FakePayoutProvider::class);

    expect(fn () => app(PayoutProviderResolver::class)->resolve('missing-provider'))
        ->toThrow(InvalidArgumentException::class, 'Unknown x-change payout provider [missing-provider]. Use a class name or one of: netbank, paynamics.');
});

it('binds the active payout provider through an emi registry alias', function () {
    config()->set('x-change.payout.provider', 'fake_alias');
    config()->set('emi.payout_providers.fake_alias', FakePayoutProvider::class);

    app()->forgetInstance(PayoutProvider::class);

    expect(app(PayoutProvider::class))->toBeInstanceOf(FakePayoutProvider::class);
});
