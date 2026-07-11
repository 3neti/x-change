<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\Cockpit\CockpitOperatorIssuanceActivityRuntimeProfileInspector;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityActionHandoffStatusProjector;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityFeedbackHandoffStatusProjector;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityJournalHandoffStatusProjector;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRecorder;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityActionHandoff;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityFeedbackHandoff;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityJournalHandoff;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityRecorder;
use LBHurtado\XChange\Services\Cockpit\NullCockpitOperatorIssuanceActivityRepository;
use LBHurtado\XChange\Services\Cockpit\XActionCockpitOperatorIssuanceActivityActionHandoff;
use LBHurtado\XChange\Services\Cockpit\XFeedbackCockpitOperatorIssuanceActivityFeedbackHandoff;
use LBHurtado\XChange\Services\Cockpit\XJournalCockpitOperatorIssuanceActivityJournalHandoff;

it('reports the operator activity runtime profile as safe and not wired by default', function () {
    $profile = app(CockpitOperatorIssuanceActivityRuntimeProfileInspector::class)->inspect();

    expect($profile->schema)->toBe('x-change.cockpit.operator-issuance-activity-runtime-profile.v1')
        ->and($profile->status)->toBe('not_wired')
        ->and($profile->repository_enabled)->toBeFalse()
        ->and($profile->recorder_enabled)->toBeFalse()
        ->and($profile->journal_handoff_enabled)->toBeFalse()
        ->and($profile->action_handoff_enabled)->toBeFalse()
        ->and($profile->feedback_handoff_enabled)->toBeFalse()
        ->and($profile->safety)->toMatchArray([
            'defaults_safe' => true,
            'requires_explicit_opt_in' => true,
            'moves_money' => false,
            'calls_provider' => false,
            'executes_action' => false,
            'sends_feedback' => false,
            'writes_journal' => false,
            'owns_lifecycle_truth' => false,
        ])
        ->and(profileComponent($profile->components, 'repository')['resolved_class'])->toBe(NullCockpitOperatorIssuanceActivityRepository::class)
        ->and(profileComponent($profile->components, 'recorder')['resolved_class'])->toBe(NullCockpitOperatorIssuanceActivityRecorder::class)
        ->and(profileComponent($profile->components, 'journal_handoff')['resolved_class'])->toBe(NullCockpitOperatorIssuanceActivityJournalHandoff::class)
        ->and(profileComponent($profile->components, 'action_handoff')['resolved_class'])->toBe(NullCockpitOperatorIssuanceActivityActionHandoff::class)
        ->and(profileComponent($profile->components, 'feedback_handoff')['resolved_class'])->toBe(NullCockpitOperatorIssuanceActivityFeedbackHandoff::class);
});

it('reports the combined runtime profile when all operator activity handoffs are explicitly enabled', function () {
    enableCombinedProfileInspection();

    $profile = app(CockpitOperatorIssuanceActivityRuntimeProfileInspector::class)->inspect();

    expect($profile->status)->toBe('combined_runtime_ready')
        ->and($profile->repository_enabled)->toBeTrue()
        ->and($profile->recorder_enabled)->toBeTrue()
        ->and($profile->journal_handoff_enabled)->toBeTrue()
        ->and($profile->action_handoff_enabled)->toBeTrue()
        ->and($profile->feedback_handoff_enabled)->toBeTrue()
        ->and($profile->safety)->toMatchArray([
            'defaults_safe' => false,
            'requires_explicit_opt_in' => true,
            'moves_money' => false,
            'calls_provider' => false,
            'executes_action' => false,
            'sends_feedback' => false,
            'writes_journal' => true,
            'owns_lifecycle_truth' => false,
        ])
        ->and(profileComponent($profile->components, 'repository')['configured'])->toBe('database')
        ->and(profileComponent($profile->components, 'repository')['resolved_class'])->toBe(DatabaseCockpitOperatorIssuanceActivityRepository::class)
        ->and(profileComponent($profile->components, 'recorder')['resolved_class'])->toBe(DatabaseCockpitOperatorIssuanceActivityRecorder::class)
        ->and(profileComponent($profile->components, 'journal_handoff')['resolved_class'])->toBe(XJournalCockpitOperatorIssuanceActivityJournalHandoff::class)
        ->and(profileComponent($profile->components, 'journal_handoff_status_projector')['resolved_class'])->toBe(DatabaseCockpitOperatorIssuanceActivityJournalHandoffStatusProjector::class)
        ->and(profileComponent($profile->components, 'action_handoff')['resolved_class'])->toBe(XActionCockpitOperatorIssuanceActivityActionHandoff::class)
        ->and(profileComponent($profile->components, 'action_handoff_status_projector')['resolved_class'])->toBe(DatabaseCockpitOperatorIssuanceActivityActionHandoffStatusProjector::class)
        ->and(profileComponent($profile->components, 'feedback_handoff')['resolved_class'])->toBe(XFeedbackCockpitOperatorIssuanceActivityFeedbackHandoff::class)
        ->and(profileComponent($profile->components, 'feedback_handoff_status_projector')['resolved_class'])->toBe(DatabaseCockpitOperatorIssuanceActivityFeedbackHandoffStatusProjector::class);
});

/**
 * @param  array<int, array<string, mixed>>  $components
 * @return array<string, mixed>
 */
function profileComponent(array $components, string $key): array
{
    $component = collect($components)->firstWhere('key', $key);

    expect($component)->toBeArray();

    return $component;
}

function enableCombinedProfileInspection(): void
{
    config()->set('x-change.cockpit.operator_issuance_activity.repository', 'database');
    config()->set('x-change.cockpit.operator_issuance_activity.recorder', 'database');
    config()->set('x-change.cockpit.operator_issuance_activity.journal_handoff', 'x-journal');
    config()->set('x-change.cockpit.operator_issuance_activity.journal_handoff_status_projector', 'database');
    config()->set('x-change.cockpit.operator_issuance_activity.action_handoff', 'x-action');
    config()->set('x-change.cockpit.operator_issuance_activity.action_handoff_status_projector', 'database');
    config()->set('x-change.cockpit.operator_issuance_activity.feedback_handoff', 'x-feedback');
    config()->set('x-change.cockpit.operator_issuance_activity.feedback_handoff_status_projector', 'database');
}
