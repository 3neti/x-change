<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository;
use LBHurtado\XChange\Services\PublishedAssetDriftDetector;

it('reports x-change doctor checks as json', function () {
    $this->artisan('x-change:doctor --json')
        ->assertExitCode(0);
});

it('reports published cockpit asset drift as json', function () {
    $exitCode = Artisan::call('x-change:doctor', [
        '--assets' => true,
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($payload['checks'][0]['name'])->toBe('published cockpit assets')
        ->and($payload['checks'][0]['meta'])->toHaveKeys(['summary', 'files']);
});

it('keeps ordinary diagnostics non-blocking while reporting failed readiness', function () {
    app()->instance(
        PublishedAssetDriftDetector::class,
        failingPublishedAssetDriftDetector(),
    );

    $exitCode = Artisan::call('x-change:doctor', [
        '--assets' => true,
        '--json' => true,
    ]);
    $payload = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($payload['schema'])->toBe('x-change.readiness-report.v1')
        ->and($payload['success'])->toBeFalse()
        ->and($payload['strict'])->toBeFalse()
        ->and($payload['summary'])->toBe([
            'passed' => 0,
            'failed' => 1,
        ]);
});

it('blocks deployment in strict mode when any readiness check fails', function () {
    app()->instance(
        PublishedAssetDriftDetector::class,
        failingPublishedAssetDriftDetector(),
    );

    $exitCode = Artisan::call('x-change:doctor', [
        '--assets' => true,
        '--strict' => true,
        '--json' => true,
    ]);
    $payload = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(1)
        ->and($payload['success'])->toBeFalse()
        ->and($payload['strict'])->toBeTrue()
        ->and($payload['checks'][0]['passed'])->toBeFalse();
});

it('reports unsafe synchronous queues and local scheduler locks', function () {
    config()->set('queue.default', 'sync');
    config()->set('cache.default', 'array');

    Artisan::call('x-change:doctor', ['--json' => true]);

    $checks = collect(json_decode(Artisan::output(), true)['checks']);
    $queue = $checks->firstWhere('name', 'durable queue runtime');
    $cache = $checks->firstWhere('name', 'shared scheduler lock cache');

    expect($queue['passed'])->toBeFalse()
        ->and($queue['meta']['required_queues'])->toBe([
            'default',
            'x-change-feedback',
            'x-change-funding',
        ])
        ->and($cache['passed'])->toBeFalse();
});

it('accepts durable queues and a shared scheduler lock cache', function () {
    config()->set('queue.default', 'database');
    config()->set('cache.default', 'database');

    Artisan::call('x-change:doctor', ['--json' => true]);

    $checks = collect(json_decode(Artisan::output(), true)['checks']);

    expect($checks->firstWhere('name', 'durable queue runtime')['passed'])->toBeTrue()
        ->and($checks->firstWhere('name', 'shared scheduler lock cache')['passed'])->toBeTrue();
});

it('reports the cockpit operator activity runtime profile as an explicit doctor check', function () {
    $exitCode = Artisan::call('x-change:doctor', [
        '--operator-activity-runtime' => true,
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($payload['checks'])->toHaveCount(1)
        ->and($payload['checks'][0]['name'])->toBe('cockpit operator activity runtime profile')
        ->and($payload['checks'][0]['passed'])->toBeTrue()
        ->and($payload['checks'][0]['meta']['schema'])->toBe('x-change.cockpit.operator-issuance-activity-runtime-profile.v1')
        ->and($payload['checks'][0]['meta']['status'])->toBe('not_wired')
        ->and($payload['checks'][0]['meta']['safety']['defaults_safe'])->toBeTrue();
});

it('reports explicitly enabled cockpit operator activity runtime components through doctor', function () {
    config()->set('x-change.cockpit.operator_issuance_activity.repository', 'database');

    $exitCode = Artisan::call('x-change:doctor', [
        '--operator-activity-runtime' => true,
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true);
    $repository = collect($payload['checks'][0]['meta']['components'])->firstWhere('key', 'repository');

    expect($exitCode)->toBe(0)
        ->and($payload['checks'])->toHaveCount(1)
        ->and($payload['checks'][0]['meta']['status'])->toBe('partially_wired')
        ->and($payload['checks'][0]['meta']['repository_enabled'])->toBeTrue()
        ->and($repository['resolved_class'])->toBe(DatabaseCockpitOperatorIssuanceActivityRepository::class);
});

function failingPublishedAssetDriftDetector(): PublishedAssetDriftDetector
{
    return new class extends PublishedAssetDriftDetector
    {
        public function inspect(?array $mappings = null): array
        {
            return [
                'name' => 'published cockpit assets',
                'passed' => false,
                'message' => 'published Cockpit assets have drift from package source',
                'summary' => [
                    'checked' => 1,
                    'ok' => 0,
                    'stale' => 1,
                    'missing' => 0,
                    'extra' => 0,
                ],
                'files' => [],
            ];
        }
    };
}
