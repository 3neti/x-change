<?php

declare(strict_types=1);

use LBHurtado\XChange\Models\CommercialOffering;
use LBHurtado\XChange\Models\XChangeInstallationManifest;
use LBHurtado\XChange\Services\Commercial\CommercialEconomicsReadiness;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XChange\Services\Configuration\CommissioningConfigurationFingerprint;
use LBHurtado\XChange\Services\Configuration\CommissioningStateResolver;

beforeEach(function (): void {
    config()->set('x-change.commercial.legal_trace.legal_entity_reference', 'legal-entity:x-change:test');
    config()->set('x-change.commercial.legal_trace.profile_version', 'test-v1');
    app(ProvisionCommercialBaselines::class)->provision('commissioning-manifest:test');

    XChangeInstallationManifest::query()->create([
        'key' => CommissioningStateResolver::ManifestKey,
        'manifest_version' => CommissioningStateResolver::ManifestVersion,
        'package_version' => 'test',
        'profile' => 'development',
        'active_connection_references' => [],
        'configuration_fingerprint' => app(CommissioningConfigurationFingerprint::class)->current(),
        'completed_at' => now(),
    ]);
});

it('repairs a stale installation baseline manifest and reconciles economics', function (): void {
    $offering = CommercialOffering::query()->where('profile', 'account_funding')->firstOrFail();
    $offering->forceFill([
        'manifest_hash' => str_repeat('0', 64),
        'manifest_yaml' => str_replace('account_funding', 'stale_profile', (string) $offering->manifest_yaml),
    ])->save();

    $this->artisan('x-change:commercial:repair-baseline-manifest', [
        '--profile' => 'account_funding',
        '--json' => true,
    ])->assertExitCode(0);

    $offering->refresh();

    expect($offering->manifest_hash)->not->toBe(str_repeat('0', 64))
        ->and(app(CommercialEconomicsReadiness::class)->inspect(['account_funding'])['ready'])->toBeTrue()
        ->and(ExecutionJournalEntry::query()
            ->where('event_type', 'commercial.offering.baseline_manifest_repaired')
            ->count())->toBe(1);
});

it('does not rewrite a governed revision', function (): void {
    $offering = CommercialOffering::query()->where('profile', 'account_funding')->firstOrFail();
    $offering->forceFill(['origin' => 'maker_checker_revision'])->save();

    $this->artisan('x-change:commercial:repair-baseline-manifest', [
        '--profile' => 'account_funding',
        '--json' => true,
    ])->assertExitCode(1);
});

it('is idempotent after the baseline manifest is repaired', function (): void {
    $this->artisan('x-change:commercial:repair-baseline-manifest', [
        '--profile' => 'account_funding',
        '--json' => true,
    ])->assertExitCode(0);
    $firstJournalCount = ExecutionJournalEntry::query()
        ->where('event_type', 'commercial.offering.baseline_manifest_repaired')
        ->count();

    $this->artisan('x-change:commercial:repair-baseline-manifest', [
        '--profile' => 'account_funding',
        '--json' => true,
    ])->assertExitCode(0);

    expect(ExecutionJournalEntry::query()
        ->where('event_type', 'commercial.offering.baseline_manifest_repaired')
        ->count())->toBe($firstJournalCount);
});
