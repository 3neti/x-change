<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\WithdrawalPipelineSteps;

use Closure;
use LBHurtado\Cash\Contracts\CashWithdrawalAmountResolverContract;
use LBHurtado\XChange\Adapters\VoucherWithdrawableInstrumentAdapter;
use LBHurtado\XChange\Contracts\WithdrawalPipelineStepContract;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;
use LBHurtado\XChange\Enums\WithdrawalPipelineStepGroup;
use LBHurtado\XChange\Support\WithdrawalPipeline\HasWithdrawalPipelineStepMetadata;

class ResolveWithdrawalAmountStep implements WithdrawalPipelineStepContract
{
    use HasWithdrawalPipelineStepMetadata;

    public static function group(): WithdrawalPipelineStepGroup
    {
        return WithdrawalPipelineStepGroup::CASH_DOMAIN;
    }

    public static function description(): string
    {
        return 'Resolve and validate the withdrawal amount against voucher constraints (remaining balance, slice limits, and rules).';
    }

    public function __construct(
        protected CashWithdrawalAmountResolverContract $amountResolver,
    ) {}

    public function handle(WithdrawalPipelineContextData $context, Closure $next): mixed
    {
        $amount = data_get($context->payload, 'amount');

        $withdrawAmount = $this->amountResolver->resolve(
            new VoucherWithdrawableInstrumentAdapter($context->voucher),
            $amount !== null && $amount !== '' ? (float) $amount : null,
        );

        $context->withWithdrawAmount($withdrawAmount);

        return $next($context);
    }
}
