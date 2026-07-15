<?php

declare(strict_types=1);

use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\XChange\Contracts\ExecutionResultFeedbackHandoffContract;
use LBHurtado\XChange\Contracts\ExecutionResultHandoffPipelineContract;
use LBHurtado\XChange\Services\Execution\NullExecutionResultFeedbackHandoff;
use LBHurtado\XChange\Services\Execution\XFeedbackExecutionResultFeedbackHandoff;
use Propaganistas\LaravelPhone\PhoneNumber;

it('keeps execution result feedback handoff null by default', function () {
    expect(app(ExecutionResultFeedbackHandoffContract::class))
        ->toBeInstanceOf(NullExecutionResultFeedbackHandoff::class);
});

it('resolves the x-feedback execution result handoff from configuration', function () {
    config()->set('x-change.execution_result_handoffs.feedback', 'x-feedback');
    app()->forgetInstance(ExecutionResultFeedbackHandoffContract::class);

    expect(app(ExecutionResultFeedbackHandoffContract::class))
        ->toBeInstanceOf(XFeedbackExecutionResultFeedbackHandoff::class);
});

it('prepares x-feedback delivery plans for execution results without dispatching delivery', function () {
    $result = app(XFeedbackExecutionResultFeedbackHandoff::class)->handoff(
        result: executionResultXFeedbackHandoffResult(),
        context: executionResultXFeedbackHandoffContext(),
    );

    expect($result->target)->toBe('feedback')
        ->and($result->status)->toBe('planned')
        ->and($result->execution_id)->toBe('exec-x-feedback-001')
        ->and($result->voucher_code)->toBe('PC-XFEEDBACK-HANDOFF')
        ->and($result->correlation_id)->toBe('corr-x-feedback-handoff')
        ->and($result->blocking)->toBeFalse()
        ->and($result->performed_side_effect)->toBeFalse()
        ->and($result->source)->toBe('x-feedback-execution-result-feedback-handoff')
        ->and($result->metadata['intent_key'])->toBe('execution.result.recorded')
        ->and($result->metadata['event_type'])->toBe('execution.result.recorded')
        ->and($result->metadata['delivery_boundary'])->toBe('prepare_only')
        ->and($result->metadata['planned_deliveries'])->toBe(1)
        ->and($result->metadata['channels'])->toBe(['in_app'])
        ->and($result->metadata['plan_items'])->toHaveCount(1)
        ->and($result->metadata['plan_items'][0]['channel'])->toBe('in_app')
        ->and($result->metadata['plan_items'][0]['status'])->toBe('planned')
        ->and($result->metadata['plan_items'][0]['recipient_type'])->toBe('operator')
        ->and($result->metadata['composition'])->toBe([
            'presentation_only' => true,
            'delivery_only' => false,
            'sends_feedback' => false,
            'records_lifecycle' => false,
            'owns_lifecycle_truth' => false,
        ])
        ->and($result->metadata)->not->toHaveKey('raw_payload')
        ->and($result->metadata)->not->toHaveKey('provider_payload')
        ->and($result->metadata)->not->toHaveKey('wallet');
});

it('includes x-feedback planning in the non-blocking execution result handoff summary', function () {
    config()->set('x-change.execution_result_handoffs.feedback', 'x-feedback');
    app()->forgetInstance(ExecutionResultFeedbackHandoffContract::class);
    app()->forgetInstance(ExecutionResultHandoffPipelineContract::class);

    $summary = app(ExecutionResultHandoffPipelineContract::class)->process(
        result: executionResultXFeedbackHandoffResult(),
        context: executionResultXFeedbackHandoffContext(),
    );

    expect($summary->blocks_execution)->toBeFalse()
        ->and($summary->results['feedback']->status)->toBe('planned')
        ->and($summary->results['feedback']->blocking)->toBeFalse()
        ->and($summary->results['feedback']->performed_side_effect)->toBeFalse()
        ->and($summary->toReportArray()['feedback']['metadata']['intent_key'])->toBe('execution.result.recorded')
        ->and($summary->toReportArray()['feedback']['metadata']['composition']['sends_feedback'])->toBeFalse();
});

function executionResultXFeedbackHandoffResult(): ExecutionResultData
{
    return new ExecutionResultData(
        execution_id: 'exec-x-feedback-001',
        successful: true,
        status: 'succeeded',
        driver: 'x_change_live_cash',
        events: [
            ['type' => 'execution.completed'],
        ],
    );
}

function executionResultXFeedbackHandoffContext(): ExecutionContextData
{
    return new ExecutionContextData(
        contact: Contact::fromPhoneNumber(new PhoneNumber('09173011987', 'PH')),
        voucherCode: 'PC-XFEEDBACK-HANDOFF',
        correlation: [
            'correlation_id' => 'corr-x-feedback-handoff',
        ],
    );
}
