<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository;
use LBHurtado\XChange\Support\Cockpit\CockpitReadOnlyPageProps;

it('builds a read-only cockpit runtime profile page prop contract', function () {
    $props = app(CockpitReadOnlyPageProps::class)->toRuntimeProfileArray();

    expect($props['can'])->toMatchArray([
        'view_cockpit' => true,
        'mutate_vouchers' => false,
        'execute_drivers' => false,
        'write_journal_entries' => false,
        'send_feedback' => false,
        'call_providers' => false,
        'move_money' => false,
    ])
        ->and($props['runtime_profile_read_model']['schema'])->toBe('x-change.cockpit.runtime-profile-page.v1')
        ->and($props['runtime_profile_read_model']['status'])->toBe('available')
        ->and($props['runtime_profile_read_model']['authorized'])->toBeTrue()
        ->and($props['runtime_profile_read_model']['read_only'])->toBeTrue()
        ->and($props['runtime_profile_read_model']['copy']['title'])->toBe('System Readiness')
        ->and($props['runtime_profile_read_model']['system_readiness']['schema'])->toBe('x-change.cockpit.system-readiness.v1')
        ->and($props['runtime_profile_read_model']['system_readiness']['summary']['total'])->toBeGreaterThan(0)
        ->and($props['runtime_profile_read_model']['system_readiness']['sections'])->toHaveCount(3)
        ->and($props['runtime_profile_read_model']['system_readiness']['redactions']['secrets_exposed'])->toBeFalse()
        ->and($props['runtime_profile_read_model']['system_readiness']['redactions']['performs_live_provider_checks'])->toBeFalse()
        ->and($props['runtime_profile_read_model']['profile']['schema'])->toBe('x-change.cockpit.operator-issuance-activity-runtime-profile.v1')
        ->and($props['runtime_profile_read_model']['commercial_accounting'])->toBeNull()
        ->and($props['runtime_profile_read_model']['safety'])->toMatchArray([
            'mutates_configuration' => false,
            'enables_handoffs' => false,
            'writes_journal' => false,
            'executes_action' => false,
            'sends_feedback' => false,
            'calls_provider' => false,
            'moves_money' => false,
            'owns_lifecycle_truth' => false,
        ])
        ->and($props['runtime_profile_read_model']['redactions'])->toMatchArray([
            'payloads' => 'runtime-configuration-class-names-only',
            'raw_payloads_exposed' => false,
            'provider_payloads_exposed' => false,
            'wallet_data_exposed' => false,
            'secrets_exposed' => false,
        ]);
});

it('includes explicit local runtime configuration without enabling unsafe capabilities', function () {
    config()->set('x-change.cockpit.operator_issuance_activity.repository', 'database');

    $props = app(CockpitReadOnlyPageProps::class)->toRuntimeProfileArray();
    $repository = collect($props['runtime_profile_read_model']['profile']['components'])
        ->firstWhere('key', 'repository');

    expect($props['runtime_profile_read_model']['profile']['status'])->toBe('partially_wired')
        ->and($repository['configured'])->toBe('database')
        ->and($repository['resolved_class'])->toBe(DatabaseCockpitOperatorIssuanceActivityRepository::class)
        ->and($props['runtime_profile_read_model']['safety']['enables_handoffs'])->toBeFalse()
        ->and($props['runtime_profile_read_model']['safety']['moves_money'])->toBeFalse();
});
