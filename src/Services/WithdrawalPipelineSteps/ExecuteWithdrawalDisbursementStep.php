<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\WithdrawalPipelineSteps;

use Closure;
use Illuminate\Support\Facades\Log;
use LBHurtado\XChange\Contracts\WithdrawalPipelineStepContract;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;
use LBHurtado\XChange\Enums\WithdrawalPipelineStepGroup;
use LBHurtado\XChange\Models\VoucherSliceExecution;
use LBHurtado\XChange\Services\Slices\VoucherSliceExecutionCoordinator;
use LBHurtado\XChange\Services\WithdrawalDisbursementExecutor;
use LBHurtado\XChange\Services\WithdrawalPendingDisbursementRecorder;
use LBHurtado\XChange\Support\WithdrawalPipeline\HasWithdrawalPipelineStepMetadata;
use LogicException;

class ExecuteWithdrawalDisbursementStep implements WithdrawalPipelineStepContract
{
    use HasWithdrawalPipelineStepMetadata;

    public static function group(): WithdrawalPipelineStepGroup
    {
        return WithdrawalPipelineStepGroup::EXECUTION;
    }

    public static function description(): string
    {
        return 'Execute the external payout/disbursement through the configured provider and capture the execution result.';
    }

    public function __construct(
        protected WithdrawalDisbursementExecutor $disbursementExecutor,
        protected WithdrawalPendingDisbursementRecorder $pendingDisbursementRecorder,
        protected VoucherSliceExecutionCoordinator $sliceExecutions,
    ) {}

    public function handle(WithdrawalPipelineContextData $context, Closure $next): mixed
    {
        if ($context->payoutRequest === null) {
            throw new LogicException('Withdrawal payout request must be built before disbursement execution.');
        }

        if ($context->bankAccount === null) {
            throw new LogicException('Withdrawal bank account must be resolved before disbursement execution.');
        }

        if ($context->sliceNumber === null) {
            throw new LogicException('Withdrawal slice number must be resolved before disbursement execution.');
        }

        try {
            $sliceExecutionReference = data_get($context->payload, '_slice_execution.reference');

            if (is_string($sliceExecutionReference) && $sliceExecutionReference !== '') {
                $sliceExecution = VoucherSliceExecution::query()
                    ->where('voucher_id', $context->voucher->getKey())
                    ->where('reference', $sliceExecutionReference)
                    ->firstOrFail();
                $this->sliceExecutions->begin($sliceExecution);
            }

            $disbursement = $this->disbursementExecutor->execute(
                voucher: $context->voucher,
                input: $context->payoutRequest,
                sliceNumber: $context->sliceNumber,
            );

            $context->withDisbursement($disbursement);

            return $next($context);
        } catch (\Throwable $e) {
            Log::warning('[ExecuteWithdrawalDisbursementStep] Gateway disbursement failed — recording pending', [
                'voucher' => $context->voucher->code,
                'slice' => $context->sliceNumber,
                'amount' => $context->withdrawAmount,
                'error' => $e->getMessage(),
            ]);

            $this->pendingDisbursementRecorder->record(
                $context->voucher,
                $context->payoutRequest,
                $e,
            );

            throw $e;
        }
    }
}
