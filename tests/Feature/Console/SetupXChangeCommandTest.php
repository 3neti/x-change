<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('renders the complete one-command setup plan without side effects', function (): void {
    $exitCode = Artisan::call('x-change:setup', [
        '--profile' => 'development',
        '--target' => 'local',
        '--name' => 'x-PayOut',
        '--url' => 'http://x-payout.test',
        '--dry-run' => true,
        '--json' => true,
    ]);
    $payload = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($payload['schema'])->toBe('x-change.setup.v1')
        ->and($payload['write_local_environment'])->toBeFalse()
        ->and($payload['steps'])->toBe([
            'configure',
            'preflight',
            'install',
            'frontend',
            'manifest',
            'verify',
        ]);
});

it('requires explicit local environment writes outside interactive mode', function (): void {
    $this->artisan('x-change:setup', [
        '--profile' => 'development',
        '--target' => 'local',
        '--write-env' => true,
        '--dry-run' => true,
        '--json' => true,
    ])->expectsOutputToContain('"write_local_environment": true')
        ->assertSuccessful();
});

it('routes remote environments through the deployment command', function (): void {
    $this->artisan('x-change:setup', [
        '--target' => 'laravel-cloud',
        '--json' => true,
    ])->expectsOutputToContain('x-change:setup is local-only')
        ->assertFailed();
});
