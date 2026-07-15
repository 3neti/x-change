<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use LBHurtado\XAction\Contracts\ActionRegistryContract;
use LBHurtado\XAction\Contracts\WorkflowActionContract;
use LBHurtado\XAction\Data\ActionContextData;
use LBHurtado\XAction\Data\ActionData;
use LBHurtado\XAction\Data\ActionSubjectData;
use LBHurtado\XAction\Data\ActionTargetData;
use LBHurtado\XChange\Contracts\ExecutionResultActionHandoffContract;
use LBHurtado\XChange\Contracts\ExecutionResultFeedbackHandoffContract;
use LBHurtado\XChange\Contracts\ExecutionResultHandoffPipelineContract;
use LBHurtado\XChange\Contracts\ExecutionResultHandoffSummaryJournalWriterContract;
use LBHurtado\XChange\Contracts\ExecutionResultJournalHandoffContract;
use LBHurtado\XChange\Tests\Fakes\User as FakeLifecycleUser;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

beforeEach(function () {
    config([
        'x-change.lifecycle.defaults.user_model' => FakeLifecycleUser::class,
        'x-change.lifecycle.defaults.system_user_email' => 'system@example.test',
        'x-change.lifecycle.defaults.test_user_email' => 'lester@hurtado.ph',
        'x-change.lifecycle.defaults.test_user_mobile' => '09173011987',
        'x-change.settlement.default_driver' => 'philhealth-bst',
        'x-change.settlement.drivers_path' => settlementEnvelopeDriversPath(),
        'x-change.execution_result_handoffs.journal' => 'x-journal',
        'queue.default' => 'sync',
    ]);

    app()->forgetInstance(ExecutionResultJournalHandoffContract::class);

    Artisan::call('xchange:lifecycle:prepare', [
        '--seed' => true,
    ]);
});

it('projects x-journal execution result records into cockpit dashboard activity', function () {
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'execution_settlement_envelope_contract_demo',
        '--json' => true,
    ]);

    $scenario = json_decode(Artisan::output(), true);
    $entry = ExecutionJournalEntry::query()->sole();

    actingAsTestUser();

    $response = $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Dashboard')
        ->assertJsonPath('props.dashboard_read_model.status', 'available')
        ->assertJsonPath('props.dashboard_read_model.authorized', true);

    $activities = data_get($response->json(), 'props.dashboard_read_model.activity', []);
    $executionActivity = collect($activities)->firstWhere(
        'id',
        'execution-'.data_get($scenario, 'execution.execution_id'),
    );

    expect($exitCode)->toBe(0)
        ->and($entry->event_type)->toBe('execution.result.recorded')
        ->and($executionActivity)->toBeArray()
        ->and($executionActivity['source'])->toBe('execution')
        ->and($executionActivity['label'])->toBe('Execution recorded for '.data_get($scenario, 'voucher_code'))
        ->and($executionActivity['description'])->toContain('settlement_envelope succeeded')
        ->and($executionActivity['description'])->toContain(data_get($scenario, 'execution.execution_id'))
        ->and($executionActivity['timestamp'])->toBe($entry->occurred_at->toISOString())
        ->and($executionActivity['metadata']['execution_handoff_profile']['targets'])->toBe([
            'journal' => 'recorded',
            'action' => 'not_wired',
            'feedback' => 'not_wired',
            'cockpit_activity' => 'not_wired',
        ])
        ->and($executionActivity['metadata']['execution_handoff_profile']['performed_side_effect_targets'])->toBe(['journal'])
        ->and($executionActivity['metadata']['execution_handoff_profile']['projection']['read_only'])->toBeTrue()
        ->and($executionActivity['metadata']['execution_handoff_profile']['projection']['executes_actions'])->toBeFalse()
        ->and($executionActivity['metadata']['execution_handoff_profile']['projection']['sends_feedback'])->toBeFalse()
        ->and($executionActivity['metadata']['execution_handoff_profile']['durable_evidence']['journal']['status'])->toBe('projected')
        ->and($executionActivity['metadata']['execution_handoff_profile']['durable_evidence']['journal']['durable'])->toBeTrue()
        ->and($executionActivity['metadata']['execution_handoff_profile']['durable_evidence']['action']['status'])->toBe('not_wired')
        ->and($executionActivity['metadata']['execution_handoff_profile']['durable_evidence']['action']['durable'])->toBeFalse()
        ->and($executionActivity['metadata']['execution_handoff_profile']['durable_evidence']['feedback']['status'])->toBe('not_wired')
        ->and($executionActivity['metadata']['execution_handoff_profile']['durable_evidence']['feedback']['durable'])->toBeFalse();
});

it('projects combined execution handoff profile status into cockpit dashboard activity', function () {
    config([
        'x-change.execution_result_handoffs.action' => 'x-action',
        'x-change.execution_result_handoffs.feedback' => 'x-feedback',
    ]);

    app()->forgetInstance(ExecutionResultActionHandoffContract::class);
    app()->forgetInstance(ExecutionResultFeedbackHandoffContract::class);

    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'execution_settlement_envelope_contract_demo',
        '--json' => true,
    ]);

    $scenario = json_decode(Artisan::output(), true);

    actingAsTestUser();

    $response = $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Dashboard');

    $activities = data_get($response->json(), 'props.dashboard_read_model.activity', []);
    $executionActivity = collect($activities)->firstWhere(
        'id',
        'execution-'.data_get($scenario, 'execution.execution_id'),
    );

    expect($exitCode)->toBe(0)
        ->and($executionActivity)->toBeArray()
        ->and($executionActivity['metadata']['execution_handoff_profile']['schema'])->toBe('x-change.cockpit.execution-handoff-profile.v1')
        ->and($executionActivity['metadata']['execution_handoff_profile']['targets'])->toBe([
            'journal' => 'recorded',
            'action' => 'enabled_not_projected',
            'feedback' => 'enabled_not_projected',
            'cockpit_activity' => 'not_wired',
        ])
        ->and($executionActivity['metadata']['execution_handoff_profile']['active_targets'])->toBe([
            'journal',
            'action',
            'feedback',
        ])
        ->and($executionActivity['metadata']['execution_handoff_profile']['performed_side_effect_targets'])->toBe(['journal'])
        ->and($executionActivity['metadata']['execution_handoff_profile']['failed_targets'])->toBe([])
        ->and($executionActivity['metadata']['execution_handoff_profile']['non_blocking'])->toBeTrue()
        ->and($executionActivity['metadata']['execution_handoff_profile']['projection'])->toBe([
            'source' => 'x-journal.execution.result.recorded',
            'action_feedback_evidence' => 'runtime-config-only',
            'read_only' => true,
            'executes_actions' => false,
            'sends_feedback' => false,
            'moves_money' => false,
        ])
        ->and($executionActivity['metadata']['execution_handoff_profile']['durable_evidence']['journal'])->toBe([
            'status' => 'projected',
            'source' => 'x-journal.execution.result.recorded',
            'durable' => true,
            'reason' => 'The Cockpit activity row is projected from a persisted execution journal entry.',
        ])
        ->and($executionActivity['metadata']['execution_handoff_profile']['durable_evidence']['action']['status'])->toBe('deferred')
        ->and($executionActivity['metadata']['execution_handoff_profile']['durable_evidence']['action']['source'])->toBeNull()
        ->and($executionActivity['metadata']['execution_handoff_profile']['durable_evidence']['action']['durable'])->toBeFalse()
        ->and($executionActivity['metadata']['execution_handoff_profile']['durable_evidence']['action']['required_source'])
        ->toBe('future x-action read model, journal event, or durable handoff evidence record')
        ->and($executionActivity['metadata']['execution_handoff_profile']['durable_evidence']['feedback']['status'])->toBe('deferred')
        ->and($executionActivity['metadata']['execution_handoff_profile']['durable_evidence']['feedback']['source'])->toBeNull()
        ->and($executionActivity['metadata']['execution_handoff_profile']['durable_evidence']['feedback']['durable'])->toBeFalse()
        ->and($executionActivity['metadata']['execution_handoff_profile']['durable_evidence']['feedback']['required_source'])
        ->toBe('future x-feedback read model, journal event, or durable handoff evidence record')
        ->and($executionActivity['metadata']['execution_handoff_profile']['durable_evidence']['action']['selected_source'])->toBe([
            'source' => 'post_pipeline_summary_journal_event',
            'status' => 'selected_not_implemented',
            'event_type' => 'execution.handoff.summary.recorded',
            'reason' => 'Selected source for future durable action/feedback handoff evidence projection.',
            'writes_now' => false,
            'read_only' => true,
        ])
        ->and($executionActivity['metadata']['execution_handoff_profile']['durable_evidence']['feedback']['selected_source'])->toBe([
            'source' => 'post_pipeline_summary_journal_event',
            'status' => 'selected_not_implemented',
            'event_type' => 'execution.handoff.summary.recorded',
            'reason' => 'Selected source for future durable action/feedback handoff evidence projection.',
            'writes_now' => false,
            'read_only' => true,
        ]);
});

it('projects configured durable handoff evidence source selection without writing evidence', function () {
    config([
        'x-change.execution_result_handoffs.action' => 'x-action',
        'x-change.execution_result_handoffs.feedback' => 'x-feedback',
        'x-change.execution_result_handoffs.durable_evidence_source' => 'post_pipeline_summary_journal_event',
        'x-change.execution_result_handoffs.durable_evidence_event_type' => 'execution.handoff.summary.recorded',
    ]);

    app()->forgetInstance(ExecutionResultActionHandoffContract::class);
    app()->forgetInstance(ExecutionResultFeedbackHandoffContract::class);

    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'execution_settlement_envelope_contract_demo',
        '--json' => true,
    ]);

    $scenario = json_decode(Artisan::output(), true);

    actingAsTestUser();

    $response = $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Dashboard');

    $activities = data_get($response->json(), 'props.dashboard_read_model.activity', []);
    $executionActivity = collect($activities)->firstWhere(
        'id',
        'execution-'.data_get($scenario, 'execution.execution_id'),
    );

    expect($exitCode)->toBe(0)
        ->and($executionActivity)->toBeArray()
        ->and($executionActivity['metadata']['execution_handoff_profile']['durable_evidence']['action']['status'])->toBe('deferred')
        ->and($executionActivity['metadata']['execution_handoff_profile']['durable_evidence']['action']['selected_source']['source'])
        ->toBe('post_pipeline_summary_journal_event')
        ->and($executionActivity['metadata']['execution_handoff_profile']['durable_evidence']['action']['selected_source']['event_type'])
        ->toBe('execution.handoff.summary.recorded')
        ->and($executionActivity['metadata']['execution_handoff_profile']['durable_evidence']['action']['selected_source']['writes_now'])
        ->toBeFalse()
        ->and($executionActivity['metadata']['execution_handoff_profile']['durable_evidence']['feedback']['status'])->toBe('deferred')
        ->and($executionActivity['metadata']['execution_handoff_profile']['durable_evidence']['feedback']['selected_source']['source'])
        ->toBe('post_pipeline_summary_journal_event')
        ->and($executionActivity['metadata']['execution_handoff_profile']['durable_evidence']['feedback']['selected_source']['event_type'])
        ->toBe('execution.handoff.summary.recorded')
        ->and($executionActivity['metadata']['execution_handoff_profile']['durable_evidence']['feedback']['selected_source']['writes_now'])
        ->toBeFalse();
});

it('projects durable post pipeline handoff summary evidence into cockpit dashboard activity', function () {
    config([
        'x-change.execution_result_handoffs.action' => 'x-action',
        'x-change.execution_result_handoffs.feedback' => 'x-feedback',
        'x-change.execution_result_handoffs.summary_journal_writer' => 'x-journal',
        'x-change.execution_result_handoffs.durable_evidence_source' => 'post_pipeline_summary_journal_event',
        'x-change.execution_result_handoffs.durable_evidence_event_type' => 'execution.handoff.summary.recorded',
    ]);

    app()->forgetInstance(ExecutionResultActionHandoffContract::class);
    app()->forgetInstance(ExecutionResultFeedbackHandoffContract::class);
    app()->forgetInstance(ExecutionResultHandoffPipelineContract::class);
    app()->forgetInstance(ExecutionResultHandoffSummaryJournalWriterContract::class);

    Route::get('/x/cockpit/pay-codes/{code}', fn (string $code): string => $code)
        ->name('x-change.cockpit.pay-codes.show');

    app(ActionRegistryContract::class)->register(
        'execution.result.recorded',
        new CockpitDurableSummaryWorkflowAction,
    );

    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'execution_settlement_envelope_contract_demo',
        '--json' => true,
    ]);

    $scenario = json_decode(Artisan::output(), true);
    $summaryEntry = ExecutionJournalEntry::query()
        ->where('event_type', 'execution.handoff.summary.recorded')
        ->sole();

    actingAsTestUser();

    $response = $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Dashboard');

    $activities = data_get($response->json(), 'props.dashboard_read_model.activity', []);
    $executionActivity = collect($activities)->firstWhere(
        'id',
        'execution-'.data_get($scenario, 'execution.execution_id'),
    );
    $profile = $executionActivity['metadata']['execution_handoff_profile'];

    expect($exitCode)->toBe(0)
        ->and($summaryEntry->payload['execution_id'])->toBe(data_get($scenario, 'execution.execution_id'))
        ->and($executionActivity)->toBeArray()
        ->and($profile['targets'])->toBe([
            'journal' => 'recorded',
            'action' => 'composed',
            'feedback' => 'planned',
            'cockpit_activity' => 'not_wired',
            'handoff_summary_journal' => 'recorded',
        ])
        ->and($profile['active_targets'])->toBe([
            'journal',
            'action',
            'feedback',
            'handoff_summary_journal',
        ])
        ->and($profile['performed_side_effect_targets'])->toBe([
            'journal',
            'handoff_summary_journal',
        ])
        ->and($profile['projection']['source'])->toBe('x-journal.execution.handoff.summary.recorded')
        ->and($profile['projection']['action_feedback_evidence'])->toBe('durable-summary-journal-event')
        ->and($profile['durable_evidence']['action']['status'])->toBe('projected')
        ->and($profile['durable_evidence']['action']['source'])->toBe('x-journal.execution.handoff.summary.recorded')
        ->and($profile['durable_evidence']['action']['durable'])->toBeTrue()
        ->and($profile['durable_evidence']['action']['event_type'])->toBe('execution.handoff.summary.recorded')
        ->and($profile['durable_evidence']['feedback']['status'])->toBe('projected')
        ->and($profile['durable_evidence']['feedback']['source'])->toBe('x-journal.execution.handoff.summary.recorded')
        ->and($profile['durable_evidence']['feedback']['durable'])->toBeTrue()
        ->and($profile['durable_evidence']['feedback']['event_type'])->toBe('execution.handoff.summary.recorded')
        ->and($profile['durable_evidence']['handoff_summary_journal']['status'])->toBe('projected')
        ->and($profile['durable_evidence']['handoff_summary_journal']['reference_number'])->toBe($summaryEntry->reference_number);
});

class CockpitDurableSummaryWorkflowAction implements WorkflowActionContract
{
    public function key(): string
    {
        return 'execution.pay-code.inspect.durable-summary';
    }

    public function supports(ActionSubjectData $subject, ActionContextData $context): bool
    {
        return $subject->type === 'execution_result'
            && $context->feature_profile === 'execution'
            && $context->hasCapability('cockpit.pay-code.open')
            && $subject->get('attributes.voucher_code') !== null;
    }

    public function toActionData(ActionSubjectData $subject, ActionContextData $context): ActionData
    {
        return new ActionData(
            key: $this->key(),
            label: 'Inspect Pay Code',
            target: new ActionTargetData(
                type: ActionTargetData::TypeRoute,
                route: 'x-change.cockpit.pay-codes.show',
                parameters: ['code' => (string) $subject->get('attributes.voucher_code')],
            ),
            intent: 'inspect',
            description: 'Open the Pay Code detail screen for read-only execution follow-up.',
            surface: 'cockpit',
            permissions: ['cockpit.pay-code.open'],
            meta: [
                'read_only' => true,
                'executes_action' => false,
            ],
        );
    }
}
