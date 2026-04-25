<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\WithdrawalPipelineSteps;

use Closure;
use Illuminate\Support\Facades\Log;
use LogicException;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;
use LBHurtado\XChange\Services\WithdrawalDisbursementExecutor;
use LBHurtado\XChange\Services\WithdrawalPendingDisbursementRecorder;

class ExecuteWithdrawalDisbursementStep
{
    public function __construct(
        protected WithdrawalDisbursementExecutor $disbursementExecutor,
        protected WithdrawalPendingDisbursementRecorder $pendingDisbursementRecorder,
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
