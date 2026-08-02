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
            ->and($manifest['schema'])->toBe('3neti.x-change.deployment.v2')
            ->and($manifest['recipe']['schema'])->toBe('3neti.x-change.cloud-recipe.v1')
            ->and($manifest['recipe']['hash'])->toHaveLength(64)
            ->and($manifest['manifest_hash'])->toHaveLength(64)
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

it('upgrades legacy manifests and preserves explicit host metadata', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'xchange-deployment-');
    file_put_contents($path, Yaml::dump([
        'schema' => '3neti.x-change.deployment.v1',
        'host' => ['owner' => 'Bank DevOps'],
        'deployment' => ['target' => 'local', 'profile' => 'development'],
        'environment' => ['secrets' => ['keys' => []]],
        'operations' => ['deploy' => ['validate']],
        'safety' => [
            'write_production_env' => false,
            'automatic_database_reset' => false,
        ],
    ]));

    try {
        $this->artisan('x-change:deployment:validate', [
            '--path' => $path,
            '--json' => true,
        ])->assertSuccessful();

        $this->artisan('x-change:deployment:generate', [
            '--target' => 'local',
            '--profile' => 'development',
            '--path' => $path,
            '--json' => true,
        ])->assertSuccessful();

        $manifest = Yaml::parseFile($path);

        expect($manifest['schema'])->toBe('3neti.x-change.deployment.v2')
            ->and($manifest['host'])->toBe(['owner' => 'Bank DevOps'])
            ->and($manifest['manifest_hash'])->toHaveLength(64);
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
