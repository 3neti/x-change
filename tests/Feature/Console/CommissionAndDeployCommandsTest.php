<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;

it('exposes a side-effect-free commissioning plan', function (): void {
    $exitCode = Artisan::call('x-change:commission', [
        '--profile' => 'netbank',
        '--dry-run' => true,
        '--json' => true,
    ]);
    $payload = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($payload['schema'])->toBe('x-change.commission.v1')
        ->and($payload['status'])->toBe('planned')
        ->and($payload['steps'])->toBe(['preflight', 'install', 'verify']);
});

it('requires production confirmation before invoking the platform', function (): void {
    Process::fake();
    $path = tempnam(sys_get_temp_dir(), 'xchange-deployment-');

    try {
        $this->artisan('x-change:deployment:generate', [
            '--target' => 'laravel-cloud',
            '--profile' => 'development',
            '--path' => $path,
        ])->assertSuccessful();

        $this->artisan('x-change:deploy', [
            'environment' => 'production',
            '--path' => $path,
            '--json' => true,
        ])->expectsOutputToContain('Production deployment was not confirmed')
            ->assertFailed();

        Process::assertNothingRan();
    } finally {
        @unlink($path);
    }
});

it('deploys monitors and commissions through whitelisted cloud commands', function (): void {
    Process::fake();
    $path = tempnam(sys_get_temp_dir(), 'xchange-deployment-');

    try {
        $this->artisan('x-change:deployment:generate', [
            '--target' => 'laravel-cloud',
            '--profile' => 'development',
            '--path' => $path,
        ])->assertSuccessful();

        $this->artisan('x-change:deploy', [
            'environment' => 'staging',
            '--application' => 'x-payout',
            '--path' => $path,
            '--confirm-production' => true,
            '--json' => true,
        ])->expectsOutputToContain('"status": "operational"')
            ->assertSuccessful();

        Process::assertRan(fn ($process): bool => $process->command === [
            'cloud', 'deploy', 'x-payout', 'staging', '-n',
        ]);
        Process::assertRan(fn ($process): bool => $process->command === [
            'cloud', 'deploy:monitor', 'x-payout', 'staging', '-n',
        ]);
        Process::assertRan(fn ($process): bool => $process->command === [
            'cloud',
            'command:run',
            'staging',
            '--cmd=php artisan x-change:commission --no-interaction',
            '-n',
        ]);
    } finally {
        @unlink($path);
    }
});
