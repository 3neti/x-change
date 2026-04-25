<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\WithdrawalPipelineSteps;

use Closure;
use LBHurtado\XChange\Contracts\WithdrawalPipelineStepContract;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;
use LBHurtado\XChange\Enums\WithdrawalPipelineStepGroup;
use LBHurtado\XChange\Services\WithdrawalBankAccountResolver;
use LBHurtado\XChange\Support\WithdrawalPipeline\HasWithdrawalPipelineStepMetadata;
use LogicException;

class ResolveWithdrawalBankAccountStep implements WithdrawalPipelineStepContract
{
    use HasWithdrawalPipelineStepMetadata;

    public static function group(): WithdrawalPipelineStepGroup
    {
        return WithdrawalPipelineStepGroup::INTEGRATION;
    }

    public static function description(): string
    {
        return 'Resolve and validate the payout destination (bank account, identifiers, and recipient details) from the request.';
    }

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
