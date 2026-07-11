<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository;

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
