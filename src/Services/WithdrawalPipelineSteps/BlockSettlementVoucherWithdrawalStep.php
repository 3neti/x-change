<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\WithdrawalPipelineSteps;

use Closure;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;
use LBHurtado\XChange\Services\SettlementCollectionGate;

class BlockSettlementVoucherWithdrawalStep
{
    public function __construct(
        protected SettlementCollectionGate $settlementCollectionGate,
    ) {}

    public static function shouldRun(WithdrawalPipelineContextData $context): bool
    {
        return true;
    }

    public function handle(WithdrawalPipelineContextData $context, Closure $next): mixed
    {
        $this->settlementCollectionGate->assertSettlementDoesNotDisburse(
            $context->voucher,
        );

        return $next($context);
    }
}
