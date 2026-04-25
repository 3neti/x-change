<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\WithdrawalPipelineSteps;

use Closure;
use LBHurtado\Cash\Contracts\CashWithdrawalEligibilityContract;
use LBHurtado\XChange\Adapters\VoucherWithdrawableInstrumentAdapter;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;

class AssertWithdrawalEligibilityStep
{
    public function __construct(
        protected CashWithdrawalEligibilityContract $withdrawalEligibility,
    ) {}

    public function handle(WithdrawalPipelineContextData $context, Closure $next): mixed
    {
        $this->withdrawalEligibility->assertEligible(
            new VoucherWithdrawableInstrumentAdapter($context->voucher),
        );

        return $next($context);
    }
}
