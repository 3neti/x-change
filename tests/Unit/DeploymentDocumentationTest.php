<?php

declare(strict_types=1);

it('documents the simple and advanced deployment workflows', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/DEPLOYMENT.md');
    $gettingStarted = file_get_contents(dirname(__DIR__, 2).'/GETTING_STARTED.md');

    expect($contents)->toContain(
        'php artisan x-change:setup',
        'php artisan x-change:deploy production',
        'php artisan x-change:configure --profile=netbank',
        'php artisan x-change:install',
        'php artisan x-change:doctor --strict',
        'php artisan x-change:commission --no-interaction',
        'x-change.deployment.yaml',
        'The simple commands do not deprecate the existing primitives.',
        'GETTING_STARTED.md',
    )
        ->and($gettingStarted)->toContain(
            "composer require '3neti/x-change:^1.0@beta' -W",
            'php artisan x-change:setup',
            'php artisan x-change:configure --profile=netbank',
            'php artisan x-change:doctor --pre-install --strict --no-interaction',
            'php artisan x-change:commission --no-interaction',
            'x-change-funding,x-change-feedback,default',
            'A webhook permits evidence intake; it does not itself authorize Account',
            'XCHANGE_ONBOARDING_REQUIRE_OTP=true',
            "Do not edit package code inside the host's `vendor/` directory.",
        );
});
