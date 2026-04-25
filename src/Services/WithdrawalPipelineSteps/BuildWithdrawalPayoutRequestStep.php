<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\WithdrawalPipelineSteps;

use Closure;
use LogicException;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;
use LBHurtado\XChange\Services\WithdrawalExecutionContextResolver;
use LBHurtado\XChange\Services\WithdrawalPayoutRequestFactory;

class BuildWithdrawalPayoutRequestStep
{
    public function __construct(
        protected WithdrawalExecutionContextResolver $executionContextResolver,
        protected WithdrawalPayoutRequestFactory $payoutRequestFactory,
    ) {}

    public function handle(WithdrawalPipelineContextData $context, Closure $next): mixed
    {
        if ($context->contact === null) {
            throw new LogicException('Withdrawal claimant must be resolved before payout request construction.');
        }

        if ($context->bankAccount === null) {
            throw new LogicException('Withdrawal bank account must be resolved before payout request construction.');
        }

        if ($context->withdrawAmount === null) {
            throw new LogicException('Withdrawal amount must be resolved before payout request construction.');
        }

        $executionContext = $this->executionContextResolver->resolve(
            $context->voucher,
            $context->bankAccount->getAccountNumber(),
        );

        $context->withPayoutRequest(
            $this->payoutRequestFactory->make(
                $context->voucher,
                $context->contact,
                $context->bankAccount,
                $executionContext->providerReference,
                $context->withdrawAmount,
            ),
        );

        return $next($context);
    }
}
