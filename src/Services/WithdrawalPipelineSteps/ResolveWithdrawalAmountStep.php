<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\WithdrawalPipelineSteps;

use Closure;
use LBHurtado\Cash\Contracts\CashWithdrawalAmountResolverContract;
use LBHurtado\XChange\Adapters\VoucherWithdrawableInstrumentAdapter;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;

class ResolveWithdrawalAmountStep
{
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
