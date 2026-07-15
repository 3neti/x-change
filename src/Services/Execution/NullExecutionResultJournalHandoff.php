<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\XChange\Contracts\ExecutionResultJournalHandoffContract;
use LBHurtado\XChange\Data\Execution\ExecutionResultHandoffResultData;

class NullExecutionResultJournalHandoff implements ExecutionResultJournalHandoffContract
{
    public function handoff(ExecutionResultData $result, ExecutionContextData $context): ExecutionResultHandoffResultData
    {
        return new ExecutionResultHandoffResultData(
            target: 'journal',
            execution_id: $result->execution_id,
            voucher_code: $context->voucherCode,
            correlation_id: $this->correlationId($context),
            source: 'null-execution-result-journal-handoff',
            reason: 'x-journal execution-result handoff is not wired. No journal entry is written.',
        );
    }

    private function correlationId(ExecutionContextData $context): ?string
    {
        $correlation = $context->correlation['correlation_id']
            ?? $context->correlation['idempotency_key']
            ?? $context->voucherCode;

        return is_scalar($correlation) ? (string) $correlation : null;
    }
}
