<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\Execution\ExecutionResultHandoffResultData;
use LBHurtado\XChange\Data\Execution\ExecutionResultHandoffSummaryData;
use LBHurtado\XChange\Services\Execution\ExecutionResultHandoffSummaryJournalPayloadMapper;

it('maps execution handoff summaries to a safe post pipeline journal payload contract', function () {
    $payload = app(ExecutionResultHandoffSummaryJournalPayloadMapper::class)->map(
        summary: new ExecutionResultHandoffSummaryData(
            execution_id: 'exec-summary-001',
            voucher_code: 'PC-SUMMARY',
            correlation_id: 'corr-summary',
            results: [
                'journal' => new ExecutionResultHandoffResultData(
                    target: 'journal',
                    status: 'recorded',
                    execution_id: 'exec-summary-001',
                    voucher_code: 'PC-SUMMARY',
                    correlation_id: 'corr-summary',
                    performed_side_effect: true,
                    source: 'x-journal-execution-journal-recorder',
                    reason: 'Execution result was handed off to x-journal.',
                    metadata: [
                        'journal_entry_id' => 'journal-entry-001',
                        'reference_number' => 'ERN-2026-000000001',
                        'raw_provider_payload' => ['secret' => true],
                    ],
                ),
                'action' => new ExecutionResultHandoffResultData(
                    target: 'action',
                    status: 'composed',
                    execution_id: 'exec-summary-001',
                    voucher_code: 'PC-SUMMARY',
                    correlation_id: 'corr-summary',
                    source: 'x-action-execution-result-action-handoff',
                    reason: 'x-action composed presentation-only continuation action hints for this execution result.',
                    metadata: [
                        'event_or_state' => 'execution.result.recorded',
                        'actions' => [
                            [
                                'key' => 'execution.pay-code.inspect',
                                'label' => 'Inspect Pay Code',
                                'target' => [
                                    'url' => '/x/cockpit/pay-codes/PC-SUMMARY',
                                ],
                            ],
                        ],
                        'raw_payload' => ['secret' => true],
                    ],
                ),
                'feedback' => new ExecutionResultHandoffResultData(
                    target: 'feedback',
                    status: 'planned',
                    execution_id: 'exec-summary-001',
                    voucher_code: 'PC-SUMMARY',
                    correlation_id: 'corr-summary',
                    source: 'x-feedback-execution-result-feedback-handoff',
                    reason: 'x-feedback prepared an execution result delivery plan without dispatching provider delivery.',
                    metadata: [
                        'intent_key' => 'execution.result.recorded',
                        'planned_deliveries' => 1,
                        'channels' => ['in_app'],
                        'plan_items' => [
                            [
                                'channel' => 'in_app',
                                'transport_secret' => 'do-not-leak',
                            ],
                        ],
                    ],
                ),
            ],
        ),
    );

    expect($payload->event_name)->toBe('execution.handoff.summary.recorded')
        ->and($payload->schema)->toBe('x-change.execution-handoff-summary-journal-payload.v1')
        ->and($payload->subject)->toBe([
            'type' => 'pay_code',
            'reference' => 'PC-SUMMARY',
        ])
        ->and($payload->references)->toBe([
            'execution_id' => 'exec-summary-001',
            'voucher_code' => 'PC-SUMMARY',
            'correlation_id' => 'corr-summary',
            'causation_id' => 'exec-summary-001',
        ])
        ->and($payload->payload['profile']['targets'])->toBe([
            'journal' => 'recorded',
            'action' => 'composed',
            'feedback' => 'planned',
        ])
        ->and($payload->payload['profile']['performed_side_effect_targets'])->toBe(['journal'])
        ->and($payload->payload['action']['status'])->toBe('composed')
        ->and($payload->payload['action']['metadata']['actions'][0]['key'])->toBe('execution.pay-code.inspect')
        ->and($payload->payload['action']['metadata'])->not->toHaveKey('raw_payload')
        ->and($payload->payload['feedback']['status'])->toBe('planned')
        ->and($payload->payload['feedback']['metadata']['planned_deliveries'])->toBe(1)
        ->and($payload->payload['feedback']['metadata']['plan_items'][0])->not->toHaveKey('transport_secret')
        ->and($payload->payload['journal']['metadata'])->not->toHaveKey('raw_provider_payload')
        ->and($payload->metadata['source'])->toBe('x-change.execution')
        ->and($payload->metadata['summary_event_source'])->toBe('post_pipeline_summary_journal_event')
        ->and($payload->metadata['redactions']['raw_handoff_payloads_exposed'])->toBeFalse()
        ->and($payload->metadata['redactions']['transport_secrets_exposed'])->toBeFalse();
});
