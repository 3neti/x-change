<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\WithdrawalPipelineSteps;

use Closure;
use LogicException;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;
use LBHurtado\XChange\Services\WithdrawalBankAccountResolver;

class ResolveWithdrawalBankAccountStep
{
    public function __construct(
        protected WithdrawalBankAccountResolver $bankAccountResolver,
    ) {}

    public function handle(WithdrawalPipelineContextData $context, Closure $next): mixed
    {
        if ($context->contact === null) {
            throw new LogicException('Withdrawal claimant must be resolved before bank account resolution.');
        }

        $context->withBankAccount(
            $this->bankAccountResolver->resolve(
                $context->voucher,
                $context->contact,
                $context->payload,
            ),
        );

        return $next($context);
    }
}
