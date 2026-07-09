<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

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
