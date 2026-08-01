<?php

declare(strict_types=1);

it('documents the simple and advanced deployment workflows', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/DEPLOYMENT.md');

    expect($contents)->toContain(
        'php artisan x-change:setup',
        'php artisan x-change:deploy production',
        'php artisan x-change:configure --profile=netbank',
        'php artisan x-change:install',
        'php artisan x-change:doctor --strict',
        'php artisan x-change:commission --no-interaction',
        'x-change.deployment.yaml',
        'The simple commands do not deprecate the existing primitives.',
    );
});
