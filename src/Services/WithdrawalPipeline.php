<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Closure;
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
        $steps = [];

        foreach ($this->steps as $step) {
            $stepClass = is_string($step) ? $step : $step::class;

            if (! $stepClass::shouldRun($context)) {
                $context->traceStep($stepClass, 'skipped');

                continue;
            }

            $steps[] = $this->wrapObservedStep($step, $stepClass);
        }

        try {
            return $this->pipeline
                ->send($context)
                ->through($steps)
                ->thenReturn();
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    protected function wrapObservedStep(string|object $step, string $stepClass): Closure
    {
        return function (WithdrawalPipelineContextData $context, Closure $next) use ($step, $stepClass): mixed {
            try {
                $result = app(Pipeline::class)
                    ->send($context)
                    ->through([$step])
                    ->thenReturn();

                $context->traceStep($stepClass, 'ran');

                return $next($result);
            } catch (\Throwable $e) {
                $context->traceStep($stepClass, 'failed', $e);

                throw $e;
            }
        };
    }
}
