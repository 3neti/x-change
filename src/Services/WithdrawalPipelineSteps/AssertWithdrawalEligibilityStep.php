<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\WithdrawalPipelineSteps;

use Closure;
use LBHurtado\Cash\Contracts\CashWithdrawalEligibilityContract;
use LBHurtado\XChange\Adapters\VoucherWithdrawableInstrumentAdapter;
use LBHurtado\XChange\Contracts\WithdrawalPipelineStepContract;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;
use LBHurtado\XChange\Enums\WithdrawalPipelineStepGroup;
use LBHurtado\XChange\Support\WithdrawalPipeline\HasWithdrawalPipelineStepMetadata;

class AssertWithdrawalEligibilityStep implements WithdrawalPipelineStepContract
{
    use HasWithdrawalPipelineStepMetadata;

    public static function group(): WithdrawalPipelineStepGroup
    {
        return WithdrawalPipelineStepGroup::CASH_DOMAIN;
    }

    public static function description(): string
    {
        return 'Assert that the voucher/instrument is eligible for withdrawal based on cash-domain rules (state, expiry, slices, etc.).';
    }

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
