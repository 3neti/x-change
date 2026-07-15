<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\XChange\Contracts\ExecutionResultCockpitActivityHandoffContract;
use LBHurtado\XChange\Data\Execution\ExecutionResultHandoffResultData;

class NullExecutionResultCockpitActivityHandoff implements ExecutionResultCockpitActivityHandoffContract
{
    public function handoff(ExecutionResultData $result, ExecutionContextData $context): ExecutionResultHandoffResultData
    {
        return new ExecutionResultHandoffResultData(
            target: 'cockpit_activity',
            execution_id: $result->execution_id,
            voucher_code: $context->voucherCode,
            correlation_id: $this->correlationId($context),
            source: 'null-execution-result-cockpit-activity-handoff',
            reason: 'Cockpit execution activity projection is not wired. Existing execution result remains authoritative.',
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
