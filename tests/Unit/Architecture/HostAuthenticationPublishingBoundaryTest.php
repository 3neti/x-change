<?php

declare(strict_types=1);

use LBHurtado\XChange\Providers\XChangeServiceProvider;

it('does not replace the host user model during ordinary auth publishing', function (): void {
    $provider = file_get_contents(
        (new ReflectionClass(XChangeServiceProvider::class))->getFileName(),
    );
    $authGroupStart = strpos($provider, "'x-change-host-migrations');");
    $authGroupEnd = strpos($provider, "'x-change-auth');", $authGroupStart);
    $authGroup = substr($provider, $authGroupStart, $authGroupEnd - $authGroupStart);

    expect($authGroup)
        ->not->toContain('stubs/app/Models/User.php.stub')
        ->and($provider)
        ->toContain("'x-change-auth-user-replacement'");
});

it('keeps the explicit replacement stub on the additive x-change base model', function (): void {
    $stub = file_get_contents(dirname(__DIR__, 3).'/stubs/app/Models/User.php.stub');

    expect($stub)
        ->toContain('use LBHurtado\XChange\Auth\XChangeAuthenticatable;')
        ->toContain('class User extends XChangeAuthenticatable')
        ->not->toContain('Bavix\Wallet\Traits');
});

it('publishes an auth logo component backed by package-owned assets', function (): void {
    $provider = file_get_contents(
        (new ReflectionClass(XChangeServiceProvider::class))->getFileName(),
    );
    $stub = file_get_contents(
        dirname(__DIR__, 3).'/stubs/resources/js/components/AppLogoIcon.vue.stub',
    );

    expect($provider)
        ->toContain('stubs/resources/js/components/AppLogoIcon.vue.stub')
        ->toContain("resource_path('js/components/AppLogoIcon.vue')")
        ->and($stub)
        ->toContain('/vendor/x-change/images/logo-orange.png')
        ->toContain('/vendor/x-change/images/logo-silver.png')
        ->not->toContain("'/images/logo-orange.png'")
        ->not->toContain("'/images/logo-silver.png'");
});
