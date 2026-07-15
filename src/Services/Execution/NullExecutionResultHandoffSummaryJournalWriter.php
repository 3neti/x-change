<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use LBHurtado\XChange\Contracts\ExecutionResultHandoffSummaryJournalWriterContract;
use LBHurtado\XChange\Data\Execution\ExecutionResultHandoffResultData;
use LBHurtado\XChange\Data\Execution\ExecutionResultHandoffSummaryData;

class NullExecutionResultHandoffSummaryJournalWriter implements ExecutionResultHandoffSummaryJournalWriterContract
{
    public function write(ExecutionResultHandoffSummaryData $summary): ExecutionResultHandoffResultData
    {
        return new ExecutionResultHandoffResultData(
            target: 'handoff_summary_journal',
            execution_id: $summary->execution_id,
            voucher_code: $summary->voucher_code,
            correlation_id: $summary->correlation_id,
            source: 'null-execution-result-handoff-summary-journal-writer',
            reason: 'Post-pipeline execution handoff summary journal writer is not wired. No journal entry is written.',
        );
    }
}
