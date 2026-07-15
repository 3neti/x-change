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
use LBHurtado\XChange\Data\Execution\ExecutionResultHandoffResultData;
use LBHurtado\XChange\Data\Execution\ExecutionResultHandoffSummaryData;
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
        'x-change.execution_result_handoffs.action' => 'x-action',
        'x-change.execution_result_handoffs.feedback' => 'x-feedback',
        'x-change.execution_result_handoffs.summary_journal_writer' => 'x-journal',
        'queue.default' => 'sync',
    ]);

    app()->forgetInstance(ExecutionResultJournalHandoffContract::class);
    app()->forgetInstance(ExecutionResultActionHandoffContract::class);
    app()->forgetInstance(ExecutionResultFeedbackHandoffContract::class);
    app()->forgetInstance(ExecutionResultHandoffPipelineContract::class);
    app()->forgetInstance(ExecutionResultHandoffSummaryJournalWriterContract::class);

    Route::get('/x/cockpit/pay-codes/{code}', fn (string $code): string => $code)
        ->name('x-change.cockpit.pay-codes.show');

    app(ActionRegistryContract::class)->register(
        'execution.result.recorded',
        new SummaryWriterLifecycleExecutionResultWorkflowAction,
    );

    Artisan::call('xchange:lifecycle:prepare', [
        '--seed' => true,
    ]);
});

it('records a sanitized post pipeline execution handoff summary into x-journal', function () {
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'execution_settlement_envelope_contract_demo',
        '--json' => true,
    ]);

    $json = json_decode(Artisan::output(), true);
    $entries = ExecutionJournalEntry::query()
        ->orderBy('id')
        ->get();
    $resultEntry = $entries->firstWhere('event_type', 'execution.result.recorded');
    $summaryEntry = $entries->firstWhere('event_type', 'execution.handoff.summary.recorded');

    expect($exitCode)->toBe(0)
        ->and($entries)->toHaveCount(2)
        ->and($resultEntry)->not->toBeNull()
        ->and($summaryEntry)->not->toBeNull()
        ->and(data_get($json, 'execution.handoffs.blocks_execution'))->toBeFalse()
        ->and(data_get($json, 'execution.handoffs.profile.targets'))->toBe([
            'journal' => 'recorded',
            'action' => 'composed',
            'feedback' => 'planned',
            'cockpit_activity' => 'not_wired',
            'handoff_summary_journal' => 'recorded',
        ])
        ->and(data_get($json, 'execution.handoffs.profile.active_targets'))->toBe([
            'journal',
            'action',
            'feedback',
            'handoff_summary_journal',
        ])
        ->and(data_get($json, 'execution.handoffs.profile.performed_side_effect_targets'))->toBe([
            'journal',
            'handoff_summary_journal',
        ])
        ->and(data_get($json, 'execution.handoffs.handoff_summary_journal.status'))->toBe('recorded')
        ->and(data_get($json, 'execution.handoffs.handoff_summary_journal.performed_side_effect'))->toBeTrue()
        ->and(data_get($json, 'execution.handoffs.handoff_summary_journal.metadata.journal_entry_id'))->toBe((string) $summaryEntry->getKey())
        ->and(data_get($json, 'execution.handoffs.handoff_summary_journal.metadata.event_type'))->toBe('execution.handoff.summary.recorded')
        ->and($summaryEntry->subject_id)->toBe(data_get($json, 'voucher_code'))
        ->and($summaryEntry->payload['execution_id'])->toBe(data_get($json, 'execution.execution_id'))
        ->and($summaryEntry->payload['profile']['targets']['journal'])->toBe('recorded')
        ->and($summaryEntry->payload['profile']['targets']['action'])->toBe('composed')
        ->and($summaryEntry->payload['profile']['targets']['feedback'])->toBe('planned')
        ->and($summaryEntry->payload['action']['status'])->toBe('composed')
        ->and($summaryEntry->payload['feedback']['status'])->toBe('planned')
        ->and($summaryEntry->metadata['source'])->toBe('x-change.execution')
        ->and($summaryEntry->metadata['summary_event_source'])->toBe('post_pipeline_summary_journal_event')
        ->and($summaryEntry->metadata['redactions']['transport_secrets_exposed'])->toBeFalse()
        ->and($summaryEntry->payload['action']['metadata'])->not->toHaveKey('raw_payload')
        ->and($summaryEntry->payload['feedback']['metadata'])->not->toHaveKey('transport_secret');
});

it('reports durable handoff summary projection readiness in lifecycle json and human output', function () {
    $jsonExitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'execution_settlement_envelope_contract_demo',
        '--json' => true,
    ]);

    $json = json_decode(Artisan::output(), true);

    expect($jsonExitCode)->toBe(0)
        ->and(data_get($json, 'execution.projection_profile.schema'))->toBe('x-change.execution-projection-profile.v1')
        ->and(data_get($json, 'execution.projection_profile.status'))->toBe('durable_summary_evidence_available')
        ->and(data_get($json, 'execution.projection_profile.cockpit_projection.source'))->toBe('x-journal.execution.handoff.summary.recorded')
        ->and(data_get($json, 'execution.projection_profile.cockpit_projection.summary_event_type'))->toBe('execution.handoff.summary.recorded')
        ->and(data_get($json, 'execution.projection_profile.cockpit_projection.read_only'))->toBeTrue()
        ->and(data_get($json, 'execution.projection_profile.targets'))->toBe([
            'journal' => 'recorded',
            'action' => 'composed',
            'feedback' => 'planned',
            'cockpit_activity' => 'not_wired',
            'handoff_summary_journal' => 'recorded',
        ])
        ->and(data_get($json, 'execution.projection_profile.projected_targets'))->toBe([
            'journal',
            'action',
            'feedback',
            'handoff_summary_journal',
        ])
        ->and(data_get($json, 'execution.projection_profile.performed_side_effect_targets'))->toBe([
            'journal',
            'handoff_summary_journal',
        ]);

    $humanExitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'execution_settlement_envelope_contract_demo',
    ]);
    $humanOutput = Artisan::output();

    expect($humanExitCode)->toBe(0)
        ->and($humanOutput)->toContain('Execution Projection: durable_summary_evidence_available')
        ->and($humanOutput)->toContain('Cockpit Projection Source: x-journal.execution.handoff.summary.recorded')
        ->and($humanOutput)->toContain('Projected Targets: journal, action, feedback, handoff_summary_journal');
});

it('replays the same x-journal handoff summary idempotently', function () {
    $summary = new ExecutionResultHandoffSummaryData(
        execution_id: 'exec-summary-idempotent',
        voucher_code: 'PC-SUMMARY-IDEMPOTENT',
        correlation_id: 'corr-summary-idempotent',
        results: [
            'journal' => new ExecutionResultHandoffResultData(
                target: 'journal',
                status: 'recorded',
                execution_id: 'exec-summary-idempotent',
                voucher_code: 'PC-SUMMARY-IDEMPOTENT',
                correlation_id: 'corr-summary-idempotent',
                performed_side_effect: true,
                source: 'test',
                reason: 'Recorded by test.',
            ),
        ],
    );

    $first = app(ExecutionResultHandoffSummaryJournalWriterContract::class)->write($summary);
    $second = app(ExecutionResultHandoffSummaryJournalWriterContract::class)->write($summary);
    $entries = ExecutionJournalEntry::query()
        ->where('event_type', 'execution.handoff.summary.recorded')
        ->get();

    expect($first->status)->toBe('recorded')
        ->and($second->status)->toBe('recorded')
        ->and($entries)->toHaveCount(1)
        ->and($second->metadata['journal_entry_id'])->toBe($first->metadata['journal_entry_id'])
        ->and($second->metadata['reference_number'])->toBe($first->metadata['reference_number']);
});

class SummaryWriterLifecycleExecutionResultWorkflowAction implements WorkflowActionContract
{
    public function key(): string
    {
        return 'execution.pay-code.inspect.summary-writer';
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
