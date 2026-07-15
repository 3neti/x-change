<?php

declare(strict_types=1);

use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\XChange\Contracts\ExecutionResultHandoffPipelineContract;
use LBHurtado\XChange\Contracts\ExecutionResultHandoffSummaryJournalWriterContract;
use LBHurtado\XChange\Data\Execution\ExecutionResultHandoffResultData;
use LBHurtado\XChange\Data\Execution\ExecutionResultHandoffSummaryData;
use LBHurtado\XChange\Services\Execution\ExecutionResultHandoffSummaryJournalPayloadMapper;
use LBHurtado\XChange\Services\Execution\NullExecutionResultHandoffSummaryJournalWriter;
use LBHurtado\XChange\Services\Execution\XJournalExecutionResultHandoffSummaryJournalWriter;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use Propaganistas\LaravelPhone\PhoneNumber;

it('binds the post pipeline handoff summary journal writer to a null non blocking default', function () {
    expect(app(ExecutionResultHandoffSummaryJournalWriterContract::class))
        ->toBeInstanceOf(NullExecutionResultHandoffSummaryJournalWriter::class);
});

it('reports the null summary writer without recording a journal entry', function () {
    $result = app(ExecutionResultHandoffSummaryJournalWriterContract::class)->write(
        new ExecutionResultHandoffSummaryData(
            execution_id: 'exec-summary-writer-001',
            voucher_code: 'PC-SUMMARY-WRITER',
            correlation_id: 'corr-summary-writer',
        ),
    );

    expect($result)->toBeInstanceOf(ExecutionResultHandoffResultData::class)
        ->and($result->target)->toBe('handoff_summary_journal')
        ->and($result->status)->toBe('not_wired')
        ->and($result->execution_id)->toBe('exec-summary-writer-001')
        ->and($result->voucher_code)->toBe('PC-SUMMARY-WRITER')
        ->and($result->correlation_id)->toBe('corr-summary-writer')
        ->and($result->blocking)->toBeFalse()
        ->and($result->performed_side_effect)->toBeFalse()
        ->and($result->source)->toBe('null-execution-result-handoff-summary-journal-writer')
        ->and($result->reason)->toBe('Post-pipeline execution handoff summary journal writer is not wired. No journal entry is written.');
});

it('invokes the post pipeline summary writer after all execution result handoffs', function () {
    $writer = new class implements ExecutionResultHandoffSummaryJournalWriterContract
    {
        public ?ExecutionResultHandoffSummaryData $summary = null;

        public function write(ExecutionResultHandoffSummaryData $summary): ExecutionResultHandoffResultData
        {
            $this->summary = $summary;

            return new ExecutionResultHandoffResultData(
                target: 'handoff_summary_journal',
                status: 'captured_by_fake_writer',
                execution_id: $summary->execution_id,
                voucher_code: $summary->voucher_code,
                correlation_id: $summary->correlation_id,
                blocking: false,
                performed_side_effect: false,
                source: 'fake-post-pipeline-summary-writer',
                reason: 'Fake writer captured the post-pipeline handoff summary.',
            );
        }
    };

    app()->instance(ExecutionResultHandoffSummaryJournalWriterContract::class, $writer);
    app()->forgetInstance(ExecutionResultHandoffPipelineContract::class);

    $summary = app(ExecutionResultHandoffPipelineContract::class)->process(
        result: executionResultHandoffSummaryWriterResult(),
        context: executionResultHandoffSummaryWriterContext(),
    );

    expect($writer->summary)->toBeInstanceOf(ExecutionResultHandoffSummaryData::class)
        ->and($writer->summary?->results['journal']->status)->toBe('not_wired')
        ->and($writer->summary?->results['action']->status)->toBe('not_wired')
        ->and($writer->summary?->results['feedback']->status)->toBe('not_wired')
        ->and($writer->summary?->results['cockpit_activity']->status)->toBe('not_wired')
        ->and($summary->results['handoff_summary_journal']->status)->toBe('captured_by_fake_writer')
        ->and($summary->toReportArray()['handoff_summary_journal']['status'])->toBe('captured_by_fake_writer')
        ->and($summary->toReportArray()['profile']['targets']['handoff_summary_journal'])->toBe('captured_by_fake_writer');
});

it('reports x-journal summary writer failures as non blocking', function () {
    $writer = new XJournalExecutionResultHandoffSummaryJournalWriter(
        mapper: app(ExecutionResultHandoffSummaryJournalPayloadMapper::class),
        recorder: new class extends ExecutionJournalRecorder
        {
            public function __construct() {}

            public function record(ExecutionJournalEntryData $entry): ExecutionJournalEntry
            {
                throw new RuntimeException('journal unavailable');
            }
        },
    );

    $result = $writer->write(
        new ExecutionResultHandoffSummaryData(
            execution_id: 'exec-summary-writer-failure',
            voucher_code: 'PC-SUMMARY-WRITER-FAILURE',
            correlation_id: 'corr-summary-writer-failure',
        ),
    );

    expect($result->target)->toBe('handoff_summary_journal')
        ->and($result->status)->toBe('failed_non_blocking')
        ->and($result->blocking)->toBeFalse()
        ->and($result->performed_side_effect)->toBeFalse()
        ->and($result->source)->toBe('x-journal-execution-handoff-summary-writer')
        ->and($result->metadata['event_type'])->toBe('execution.handoff.summary.recorded')
        ->and($result->metadata['exception'])->toBe(RuntimeException::class);
});

function executionResultHandoffSummaryWriterResult(): ExecutionResultData
{
    return new ExecutionResultData(
        execution_id: 'exec-summary-writer-002',
        successful: true,
        status: 'succeeded',
        driver: 'x_change_live_cash',
    );
}

function executionResultHandoffSummaryWriterContext(): ExecutionContextData
{
    return new ExecutionContextData(
        contact: Contact::fromPhoneNumber(new PhoneNumber('09173011987', 'PH')),
        voucherCode: 'PC-SUMMARY-WRITER-PIPELINE',
        correlation: [
            'correlation_id' => 'corr-summary-writer-pipeline',
        ],
    );
}
