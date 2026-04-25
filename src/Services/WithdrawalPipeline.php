<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Pipeline\Pipeline;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;

/**
 * Withdrawal orchestration pipeline.
 *
 * This pipeline is the extension point for pre-execution withdrawal behavior.
 * Keep DefaultWithdrawalProcessorService focused on high-level orchestration;
 * add new withdrawal rules here as discrete pipeline steps.
 *
 * Suitable future steps:
 * - OTP verification
 * - KYC approval checks
 * - location/radius validation
 * - selfie/liveness verification
 * - fraud/risk scoring
 * - rate limiting / velocity checks
 *
 * Rule of thumb:
 * - cash-domain rules belong in 3neti/cash
 * - x-change orchestration/integration rules belong here
 * - provider execution, wallet settlement, and result shaping should remain
 *   separate services/steps
 */
class WithdrawalPipeline
{
    /**
     * @param  array<int, class-string|object>  $steps
     */
    public function __construct(
        protected Pipeline $pipeline,
        protected array $steps = [],
    ) {}

    public function process(WithdrawalPipelineContextData $context): WithdrawalPipelineContextData
    {
        return $this->pipeline
            ->send($context)
            ->through($this->steps)
            ->thenReturn();
    }
}
