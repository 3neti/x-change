<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use LBHurtado\XChange\Providers\XChangeServiceProvider;

it('publishes the host user migration before running database migrations', function () {
    $paths = ServiceProvider::pathsToPublish(
        XChangeServiceProvider::class,
        'x-change-host-migrations',
    );
    $command = file_get_contents(
        dirname(__DIR__, 3).'/src/Console/Commands/InstallXChangeCommand.php',
    );

    expect($paths)
        ->toHaveCount(1)
        ->and(array_key_first($paths))->toEndWith(
            'stubs/migrations/2026_06_17_000000_prepare_users_for_mobile_first_xchange.php.stub',
        )
        ->and(array_values($paths)[0])->toEndWith(
            'database/migrations/2026_06_17_000000_prepare_users_for_mobile_first_xchange.php',
        )
        ->and(strpos($command, '$this->publishHostMigrations($force);'))
        ->toBeLessThan(strpos($command, "if (! \$this->option('no-migrate'))"));
});
