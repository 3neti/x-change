<?php

declare(strict_types=1);

it('documents the simple and advanced deployment workflows', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/DEPLOYMENT.md');
    $gettingStarted = file_get_contents(dirname(__DIR__, 2).'/GETTING_STARTED.md');
    $readme = file_get_contents(dirname(__DIR__, 2).'/README.md');

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
            'php artisan x-change:host:adopt --dry-run --json',
            '`x-change-shell` tag',
            "Do not edit package code inside the host's `vendor/` directory.",
        )
        ->and($readme)->toContain(
            'Settlement Operating System',
            'A Pay Code does not itself hold money',
            'Provider Inventory',
            'Treasury Positions',
            'Pay Code obligations',
            'GETTING_STARTED.md',
            "composer require '3neti/x-change:^1.0@beta' -W",
            'php artisan x-change:doctor --pre-install --strict --no-interaction',
            'x-change-funding,x-change-feedback,default',
            'one responsive sidebar',
            'See [LICENSE.md](./LICENSE.md) for the authoritative license terms.',
        )
        ->and($readme)->not->toContain(
            'wallet-backed',
            'Revenue Model (For Banks)',
            'POST /pay-codes/{code}/claim/start',
        );
});

it('ships a canonical secret-free Laravel Cloud recipe', function (): void {
    $packageRoot = dirname(__DIR__, 2);
    $recipe = file_get_contents($packageRoot.'/resources/deployment/laravel-cloud.yaml');
    $compass = file_get_contents($packageRoot.'/docs/deployment/X_CHANGE_CLOUD_RECIPE.md');

    expect($recipe)->toContain('3neti.x-change.cloud-recipe.v1')
        ->toContain('x-change-funding')
        ->toContain('php artisan migrate --force')
        ->not->toContain('CLIENT_SECRET=')
        ->and($compass)->toContain('composer x-change:cloud:ship')
        ->toContain('never manufactures a balancing entry');
});

it('exposes Cloud metadata and an explicit package executable without a Composer plugin', function (): void {
    $packageRoot = dirname(__DIR__, 2);
    $composer = json_decode(file_get_contents($packageRoot.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $executable = file_get_contents($packageRoot.'/bin/x-change-cloud');

    expect($composer['bin'])->toContain('bin/x-change-cloud')
        ->and($composer['extra']['x-change']['cloud-recipe'])->toBe('resources/deployment/laravel-cloud.yaml')
        ->and($composer['extra'])->not->toHaveKey('composer-plugin')
        ->and($executable)->toContain("'x-change:cloud'")
        ->not->toContain('shell_exec');
});
