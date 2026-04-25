<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\WithdrawalPipelineSteps;

use Closure;
use LogicException;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;
use LBHurtado\XChange\Services\WithdrawalResultFactory;

class BuildWithdrawalResultStep
{
    public function __construct(
        protected WithdrawalResultFactory $resultFactory,
    ) {}

    public function handle(WithdrawalPipelineContextData $context, Closure $next): mixed
    {
        if ($context->contact === null) {
            throw new LogicException('Withdrawal claimant must be resolved before result construction.');
        }

        if ($context->payoutRequest === null) {
            throw new LogicException('Withdrawal payout request must be built before result construction.');
        }

        if ($context->withdrawAmount === null) {
            throw new LogicException('Withdrawal amount must be resolved before result construction.');
        }

        if ($context->sliceNumber === null) {
            throw new LogicException('Withdrawal slice number must be resolved before result construction.');
        }

        if ($context->disbursement === null) {
            throw new LogicException('Withdrawal disbursement must be executed before result construction.');
        }

        if ($context->settlement === null) {
            throw new LogicException('Withdrawal wallet settlement must be completed before result construction.');
        }

        $context->voucher->refresh();

        $context->withResult(
            $this->resultFactory->make(
                voucher: $context->voucher,
                contact: $context->contact,
                withdrawAmount: $context->withdrawAmount,
                sliceNumber: $context->sliceNumber,
                input: $context->payoutRequest,
                response: $context->disbursement->response,
            ),
        );

        return $next($context);
    }
}
