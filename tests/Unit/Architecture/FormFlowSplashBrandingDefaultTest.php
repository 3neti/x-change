<?php

declare(strict_types=1);

use LBHurtado\XChange\Providers\XChangeServiceProvider;

it('points the form-flow default splash logo at the published x-change asset', function (): void {
    expect(config('splash.app_logo'))->toBe('/vendor/x-change/images/logo-orange.png');
});

it('does not replace an explicit host form-flow splash logo', function (): void {
    config()->set('splash.app_logo', '/tenant/logo.svg');

    (new XChangeServiceProvider(app()))->register();

    expect(config('splash.app_logo'))->toBe('/tenant/logo.svg');
});
