<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\WithdrawalPipelineSteps;

use Closure;
use LBHurtado\XChange\Contracts\WithdrawalPipelineStepContract;
use LBHurtado\XChange\Enums\WithdrawalPipelineStepGroup;
use LBHurtado\XChange\Support\WithdrawalPipeline\HasWithdrawalPipelineStepMetadata;
use LogicException;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;
use LBHurtado\XChange\Services\WithdrawalRailGuard;

class GuardWithdrawalRailStep implements WithdrawalPipelineStepContract
{
    use HasWithdrawalPipelineStepMetadata;

    public static function group(): WithdrawalPipelineStepGroup
    {
        return WithdrawalPipelineStepGroup::INTEGRATION;
    }

    public static function description(): string
    {
        return 'Validate that the requested settlement rail (e.g., InstaPay) is allowed and supported for this withdrawal.';
    }

    public function __construct(
        protected WithdrawalRailGuard $railGuard,
    ) {}

    public function handle(WithdrawalPipelineContextData $context, Closure $next): mixed
    {
        if ($context->payoutRequest === null) {
            throw new LogicException('Withdrawal payout request must be built before rail guard.');
        }

        $this->railGuard->assertAllowed($context->payoutRequest);

        return $next($context);
    }
}
