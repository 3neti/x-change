<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\WithdrawalPipelineSteps;

use Closure;
use LogicException;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;
use LBHurtado\XChange\Services\WithdrawalRailGuard;

class GuardWithdrawalRailStep
{
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
