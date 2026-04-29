<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\SettlementReadinessGateContract;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Exceptions\VoucherRequiresSettlementEnvelope;

class DefaultSettlementReadinessGate implements SettlementReadinessGateContract
{
    public function __construct(
        protected VoucherFlowCapabilityResolverContract $flowResolver,
    ) {}

    public function assertReady(Voucher $voucher): void
    {
        $capabilities = $this->flowResolver->resolve($voucher);

        if (! $capabilities->type->isSettlement()) {
            return;
        }

        if ($capabilities->requires_envelope) {
            throw VoucherRequiresSettlementEnvelope::forVoucher($voucher, $capabilities);
        }
    }
}
