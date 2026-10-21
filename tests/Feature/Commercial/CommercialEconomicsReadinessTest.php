<?php

declare(strict_types=1);

use LBHurtado\XChange\Exceptions\CommercialEconomicsUnavailable;
use LBHurtado\XChange\Models\CommercialComponentEconomics;
use LBHurtado\XChange\Services\Commercial\CommercialEconomicsReadiness;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XChange\Models\XChangeInstallationManifest;
use LBHurtado\XChange\Services\Configuration\CommissioningConfigurationFingerprint;
use LBHurtado\XChange\Services\Configuration\CommissioningStateResolver;

it('reports all configured commercial profiles as ready after commissioning', function (): void {
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:readiness');

    $status = app(CommercialEconomicsReadiness::class)->inspect(['pay_code', 'account_funding']);

    expect($status['ready'])->toBeTrue()
        ->and($status['profiles']['pay_code']['ready'])->toBeTrue()
        ->and($status['profiles']['account_funding']['ready'])->toBeTrue()
        ->and($status['profiles']['pay_code']['offering_version'])->toBe(1);
});

it('reports a stale economics binding without exposing provider or database details', function (): void {
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:readiness-stale');
    CommercialComponentEconomics::query()
        ->where('profile', 'pay_code')
        ->update(['offering_snapshot_hash' => str_repeat('0', 64)]);

    $status = app(CommercialEconomicsReadiness::class)->inspect(['pay_code']);

    expect($status['ready'])->toBeFalse()
        ->and($status['profiles']['pay_code']['ready'])->toBeFalse()
        ->and($status['profiles']['pay_code']['reason'])->toContain('inconsistent')
        ->and($status['profiles']['pay_code']['reason'])->not->toContain('SQLSTATE');

    expect(fn () => app(\LBHurtado\XChange\Contracts\CommercialComponentEconomicsResolverContract::class)->resolve('pay_code'))
        ->toThrow(CommercialEconomicsUnavailable::class);
});

it('runs the all-profile reconciliation command idempotently', function (): void {
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:command');
    XChangeInstallationManifest::query()->create([
        'key' => CommissioningStateResolver::ManifestKey,
        'manifest_version' => 1,
        'package_version' => 'test',
        'profile' => 'test',
        'active_connection_references' => [],
        'configuration_fingerprint' => app(CommissioningConfigurationFingerprint::class)->current(),
        'completed_at' => now(),
    ]);

    $this->artisan('x-change:commercial:reconcile-profiles', ['--strict' => true, '--json' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('"success": true');
});
