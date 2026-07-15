<?php

declare(strict_types=1);

use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\XChange\Contracts\ExecutionResultActionHandoffContract;
use LBHurtado\XChange\Contracts\ExecutionResultCockpitActivityHandoffContract;
use LBHurtado\XChange\Contracts\ExecutionResultFeedbackHandoffContract;
use LBHurtado\XChange\Contracts\ExecutionResultHandoffPipelineContract;
use LBHurtado\XChange\Contracts\ExecutionResultJournalHandoffContract;
use LBHurtado\XChange\Data\Execution\ExecutionResultHandoffResultData;
use LBHurtado\XChange\Services\Execution\ExecutionResultHandoffPipeline;
use LBHurtado\XChange\Services\Execution\NullExecutionResultActionHandoff;
use LBHurtado\XChange\Services\Execution\NullExecutionResultCockpitActivityHandoff;
use LBHurtado\XChange\Services\Execution\NullExecutionResultFeedbackHandoff;
use LBHurtado\XChange\Services\Execution\NullExecutionResultJournalHandoff;
use Propaganistas\LaravelPhone\PhoneNumber;

it('binds execution result handoff pipeline with null non-blocking defaults', function () {
    expect(app(ExecutionResultHandoffPipelineContract::class))->toBeInstanceOf(ExecutionResultHandoffPipeline::class)
        ->and(app(ExecutionResultJournalHandoffContract::class))->toBeInstanceOf(NullExecutionResultJournalHandoff::class)
        ->and(app(ExecutionResultActionHandoffContract::class))->toBeInstanceOf(NullExecutionResultActionHandoff::class)
        ->and(app(ExecutionResultFeedbackHandoffContract::class))->toBeInstanceOf(NullExecutionResultFeedbackHandoff::class)
        ->and(app(ExecutionResultCockpitActivityHandoffContract::class))->toBeInstanceOf(NullExecutionResultCockpitActivityHandoff::class);
});

it('reports not wired handoffs without blocking execution result handling', function () {
    $summary = app(ExecutionResultHandoffPipelineContract::class)->process(
        result: new ExecutionResultData(
            execution_id: 'exec-001',
            successful: true,
            status: 'succeeded',
            driver: 'x_change_live_cash',
        ),
        context: executionResultHandoffContext(),
    );

    expect($summary->blocks_execution)->toBeFalse()
        ->and($summary->results['journal']->status)->toBe('not_wired')
        ->and($summary->results['journal']->blocking)->toBeFalse()
        ->and($summary->results['journal']->performed_side_effect)->toBeFalse()
        ->and($summary->results['action']->status)->toBe('not_wired')
        ->and($summary->results['feedback']->status)->toBe('not_wired')
        ->and($summary->results['cockpit_activity']->status)->toBe('not_wired')
        ->and($summary->toReportArray()['journal']['execution_id'])->toBe('exec-001')
        ->and($summary->toReportArray()['journal']['voucher_code'])->toBe('PC-HANDOFF')
        ->and($summary->toReportArray()['profile'])->toBe([
            'targets' => [
                'journal' => 'not_wired',
                'action' => 'not_wired',
                'feedback' => 'not_wired',
                'cockpit_activity' => 'not_wired',
            ],
            'active_targets' => [],
            'performed_side_effect_targets' => [],
            'failed_targets' => [],
            'non_blocking' => true,
        ]);
});

it('captures handoff exceptions as failed non blocking results', function () {
    $pipeline = new ExecutionResultHandoffPipeline(
        journal: new class implements ExecutionResultJournalHandoffContract
        {
            public function handoff(ExecutionResultData $result, ExecutionContextData $context): ExecutionResultHandoffResultData
            {
                throw new RuntimeException('journal down');
            }
        },
        action: new NullExecutionResultActionHandoff,
        feedback: new NullExecutionResultFeedbackHandoff,
        cockpitActivity: new NullExecutionResultCockpitActivityHandoff,
    );

    $summary = $pipeline->process(
        result: new ExecutionResultData(
            execution_id: 'exec-002',
            successful: true,
            status: 'succeeded',
            driver: 'x_change_live_cash',
        ),
        context: executionResultHandoffContext(),
    );

    expect($summary->results['journal']->status)->toBe('failed_non_blocking')
        ->and($summary->results['journal']->blocking)->toBeFalse()
        ->and($summary->results['journal']->performed_side_effect)->toBeFalse()
        ->and($summary->results['journal']->metadata['exception'])->toBe(RuntimeException::class)
        ->and($summary->results['action']->status)->toBe('not_wired');
});

function executionResultHandoffContext(): ExecutionContextData
{
    return new ExecutionContextData(
        contact: Contact::fromPhoneNumber(new PhoneNumber('09173011987', 'PH')),
        voucherCode: 'PC-HANDOFF',
        correlation: [
            'idempotency_key' => 'corr-handoff',
        ],
    );
}
