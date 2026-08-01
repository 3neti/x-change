<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Yaml\Yaml;

it('generates and validates a secret-free deployment manifest', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'xchange-deployment-');

    try {
        $exitCode = Artisan::call('x-change:deployment:generate', [
            '--target' => 'local',
            '--profile' => 'development',
            '--path' => $path,
            '--json' => true,
        ]);
        $contents = file_get_contents($path);
        $manifest = Yaml::parse($contents);

        expect($exitCode)->toBe(0)
            ->and($manifest['schema'])->toBe('3neti.x-change.deployment.v1')
            ->and($manifest['application']['display_name'])->toStartWith('x-')
            ->and($manifest['deployment']['target'])->toBe('local')
            ->and($manifest['deployment']['profile'])->toBe('development')
            ->and($manifest['safety']['write_production_env'])->toBeFalse()
            ->and($manifest['safety']['automatic_database_reset'])->toBeFalse()
            ->and($contents)->not->toContain('client_secret:', 'api_key:');

        $this->artisan('x-change:deployment:validate', [
            '--path' => $path,
            '--json' => true,
        ])->assertSuccessful();
    } finally {
        @unlink($path);
    }
});

it('rejects undeclared deployment operations', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'xchange-deployment-');
    file_put_contents($path, Yaml::dump([
        'schema' => '3neti.x-change.deployment.v1',
        'deployment' => ['target' => 'local'],
        'environment' => ['secrets' => ['keys' => []]],
        'operations' => ['deploy' => ['run-arbitrary-shell']],
        'safety' => [
            'write_production_env' => false,
            'automatic_database_reset' => false,
        ],
    ]));

    try {
        $this->artisan('x-change:deployment:validate', [
            '--path' => $path,
        ])->expectsOutputToContain('undeclared operation')
            ->assertFailed();
    } finally {
        @unlink($path);
    }
});
