<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('reports UTC time authority in strict doctor output', function (): void {
    Artisan::call('x-change:doctor', [
        '--pre-install' => true,
        '--strict' => true,
        '--json' => true,
    ]);
    $check = collect(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['checks'])
        ->firstWhere('name', 'time authority');

    expect($check['passed'])->toBeTrue()
        ->and($check['meta']['app_timezone'])->toBe('UTC')
        ->and($check['meta']['php_timezone'])->toBe('UTC')
        ->and($check['meta']['database_driver'])->toBe('sqlite')
        ->and($check['meta']['database_timezone'])->toBeNull();
});

it('fails strict readiness when Laravel is not governed by UTC', function (): void {
    config()->set('app.timezone', 'Asia/Manila');

    $exitCode = Artisan::call('x-change:doctor', [
        '--pre-install' => true,
        '--strict' => true,
        '--json' => true,
    ]);
    $check = collect(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['checks'])
        ->firstWhere('name', 'time authority');

    expect($exitCode)->toBe(1)
        ->and($check['passed'])->toBeFalse()
        ->and($check['message'])->toContain('must use UTC');
});
