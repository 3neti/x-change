<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\Configuration\CommissioningManifestRecorder;

it('reports commissioning status without exposing configuration values', function (): void {
    $this->artisan('x-change:commissioning:status', ['--json' => true])
        ->expectsOutputToContain('installation_incomplete')
        ->assertFailed();
});

it('records a sanitized manifest idempotently', function (): void {
    provisionTestSystemPrincipalForCommissioning();

    $recorder = app(CommissioningManifestRecorder::class);

    $first = $recorder->record();
    $second = $recorder->record();

    expect($first->key)->toBe('primary')
        ->and($second->key)->toBe($first->key)
        ->and($second->configuration_fingerprint)->toHaveLength(64)
        ->and($second->active_connection_references)->toBe([])
        ->and($second->getAttributes())->not->toHaveKey('configuration');
});

it('refuses to record a manifest without the system principal Account', function (): void {
    app(CommissioningManifestRecorder::class)->record();
})->throws(
    RuntimeException::class,
    'Commissioning requires a persisted non-interactive system principal and Account.',
);

it('refuses adoption without explicit operator confirmation', function (): void {
    $this->artisan('x-change:commissioning:adopt')->assertFailed();
});
