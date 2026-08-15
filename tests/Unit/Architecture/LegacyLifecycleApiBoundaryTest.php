<?php

declare(strict_types=1);

it('keeps the legacy lifecycle api disabled by default and bounded to non-production environments', function () {
    $config = require dirname(__DIR__, 3).'/config/x-change.php';

    expect($config['routes']['legacy_lifecycle_api'])
        ->toMatchArray([
            'enabled' => false,
            'environments' => ['local', 'testing'],
        ]);
});

it('loads the legacy lifecycle routes only through the explicit bounded gate', function () {
    $provider = file_get_contents(dirname(__DIR__, 3).'/src/Providers/XChangeServiceProvider.php');

    expect($provider)
        ->toContain('x-change.routes.legacy_lifecycle_api.enabled')
        ->toContain('x-change.routes.legacy_lifecycle_api.environments')
        ->toContain('$this->app->environment($legacyLifecycleApiEnvironments)')
        ->not->toContain("\$this->loadRoutesFrom(__DIR__.'/../../routes/lifecycle-api.php')");
});
