<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityRepository;

it('reports the default operator activity runtime profile as json', function () {
    $exitCode = Artisan::call('x-change:cockpit:operator-activity-runtime-profile', [
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($payload['schema'])->toBe('x-change.cockpit.operator-issuance-activity-runtime-profile.v1')
        ->and($payload['status'])->toBe('not_wired')
        ->and($payload['repository_enabled'])->toBeFalse()
        ->and($payload['recorder_enabled'])->toBeFalse()
        ->and($payload['journal_handoff_enabled'])->toBeFalse()
        ->and($payload['action_handoff_enabled'])->toBeFalse()
        ->and($payload['feedback_handoff_enabled'])->toBeFalse()
        ->and($payload['safety'])->toMatchArray([
            'defaults_safe' => true,
            'requires_explicit_opt_in' => true,
            'moves_money' => false,
            'calls_provider' => false,
            'executes_action' => false,
            'sends_feedback' => false,
            'writes_journal' => false,
            'owns_lifecycle_truth' => false,
        ])
        ->and(runtimeProfileCommandComponent($payload['components'], 'repository')['resolved_class'])
        ->toBe(NullCockpitOperatorIssuanceActivityRepository::class);
});

it('reports explicitly enabled operator activity runtime profile components as json', function () {
    config()->set('x-change.cockpit.operator_issuance_activity.repository', 'database');

    $exitCode = Artisan::call('x-change:cockpit:operator-activity-runtime-profile', [
        '--json' => true,
    ]);

    $payload = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($payload['status'])->toBe('partially_wired')
        ->and($payload['repository_enabled'])->toBeTrue()
        ->and($payload['recorder_enabled'])->toBeFalse()
        ->and($payload['safety']['defaults_safe'])->toBeFalse()
        ->and($payload['safety']['writes_journal'])->toBeFalse()
        ->and(runtimeProfileCommandComponent($payload['components'], 'repository'))->toMatchArray([
            'configured' => 'database',
            'enabled' => true,
            'resolved_class' => DatabaseCockpitOperatorIssuanceActivityRepository::class,
            'uses_fallback' => false,
        ]);
});

/**
 * @param  array<int, array<string, mixed>>  $components
 * @return array<string, mixed>
 */
function runtimeProfileCommandComponent(array $components, string $key): array
{
    $component = collect($components)->firstWhere('key', $key);

    expect($component)->toBeArray();

    return $component;
}
