<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\Configuration\CommissioningManifestRecorder;

it('reports commissioning status without exposing configuration values', function (): void {
    $this->artisan('x-change:commissioning:status', ['--json' => true])
        ->expectsOutputToContain('installation_incomplete')
        ->assertFailed();
});

it('records a sanitized manifest idempotently', function (): void {
    $recorder = app(CommissioningManifestRecorder::class);

    $first = $recorder->record();
    $second = $recorder->record();

    expect($first->key)->toBe('primary')
        ->and($second->key)->toBe($first->key)
        ->and($second->configuration_fingerprint)->toHaveLength(64)
        ->and($second->active_connection_references)->toBe([])
        ->and($second->getAttributes())->not->toHaveKey('configuration');
});

it('refuses adoption without explicit operator confirmation', function (): void {
    $this->artisan('x-change:commissioning:adopt')->assertFailed();
});
