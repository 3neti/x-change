<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\XChange\Contracts\ExecutionResultActionHandoffContract;
use LBHurtado\XChange\Contracts\ExecutionResultCockpitActivityHandoffContract;
use LBHurtado\XChange\Contracts\ExecutionResultFeedbackHandoffContract;
use LBHurtado\XChange\Contracts\ExecutionResultHandoffPipelineContract;
use LBHurtado\XChange\Contracts\ExecutionResultJournalHandoffContract;
use LBHurtado\XChange\Data\Execution\ExecutionResultHandoffResultData;
use LBHurtado\XChange\Data\Execution\ExecutionResultHandoffSummaryData;
use Throwable;

class ExecutionResultHandoffPipeline implements ExecutionResultHandoffPipelineContract
{
    public function __construct(
        private readonly ExecutionResultJournalHandoffContract $journal,
        private readonly ExecutionResultActionHandoffContract $action,
        private readonly ExecutionResultFeedbackHandoffContract $feedback,
        private readonly ExecutionResultCockpitActivityHandoffContract $cockpitActivity,
    ) {}

    public function process(ExecutionResultData $result, ExecutionContextData $context): ExecutionResultHandoffSummaryData
    {
        $results = [
            'journal' => $this->attempt('journal', fn (): ExecutionResultHandoffResultData => $this->journal->handoff($result, $context), $result, $context),
            'action' => $this->attempt('action', fn (): ExecutionResultHandoffResultData => $this->action->handoff($result, $context), $result, $context),
            'feedback' => $this->attempt('feedback', fn (): ExecutionResultHandoffResultData => $this->feedback->handoff($result, $context), $result, $context),
            'cockpit_activity' => $this->attempt('cockpit_activity', fn (): ExecutionResultHandoffResultData => $this->cockpitActivity->handoff($result, $context), $result, $context),
        ];

        return new ExecutionResultHandoffSummaryData(
            execution_id: $result->execution_id,
            voucher_code: $context->voucherCode,
            correlation_id: $this->correlationId($context),
            results: $results,
        );
    }

    private function attempt(string $target, callable $handoff, ExecutionResultData $result, ExecutionContextData $context): ExecutionResultHandoffResultData
    {
        try {
            return $handoff();
        } catch (Throwable $exception) {
            return new ExecutionResultHandoffResultData(
                target: $target,
                status: 'failed_non_blocking',
                execution_id: $result->execution_id,
                voucher_code: $context->voucherCode,
                correlation_id: $this->correlationId($context),
                blocking: false,
                performed_side_effect: false,
                source: 'execution-result-handoff-pipeline',
                reason: 'Execution result handoff failed without blocking the completed execution result.',
                metadata: [
                    'exception' => $exception::class,
                ],
            );
        }
    }

    private function correlationId(ExecutionContextData $context): ?string
    {
        $correlation = $context->correlation['correlation_id']
            ?? $context->correlation['idempotency_key']
            ?? $context->voucherCode;

        return is_scalar($correlation) ? (string) $correlation : null;
    }
}
