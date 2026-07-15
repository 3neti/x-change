<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\XChange\Contracts\ExecutionResultActionHandoffContract;
use LBHurtado\XChange\Data\Execution\ExecutionResultHandoffResultData;

class NullExecutionResultActionHandoff implements ExecutionResultActionHandoffContract
{
    public function handoff(ExecutionResultData $result, ExecutionContextData $context): ExecutionResultHandoffResultData
    {
        return new ExecutionResultHandoffResultData(
            target: 'action',
            execution_id: $result->execution_id,
            voucher_code: $context->voucherCode,
            correlation_id: $this->correlationId($context),
            source: 'null-execution-result-action-handoff',
            reason: 'x-action execution-result handoff is not wired. No continuation action is created.',
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
