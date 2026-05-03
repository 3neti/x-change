<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\SettlementEnvelopeReadinessContract;
use LBHurtado\XChange\Contracts\SettlementReadinessGateContract;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Exceptions\VoucherRequiresSettlementEnvelope;

class DefaultSettlementReadinessGate implements SettlementReadinessGateContract
{
    public function __construct(
        protected VoucherFlowCapabilityResolverContract $flowResolver,
        protected SettlementEnvelopeReadinessContract $readiness,
    ) {}

    public function assertReady(Voucher $voucher): void
    {
        $capabilities = $this->flowResolver->resolve($voucher);

        if (! $capabilities->type->isSettlement()) {
            return;
        }

        if (! $capabilities->requires_envelope) {
            return;
        }

        throw VoucherRequiresSettlementEnvelope::forVoucher(
            voucher: $voucher,
            readinessOrCapabilities: $capabilities,
        );
    }

    public function ensureReady(
        Voucher $voucher,
        string $gate = 'settleable',
        array $context = [],
    ): void {
        $capabilities = $this->flowResolver->resolve($voucher);

        if (! $capabilities->type->isSettlement()) {
            return;
        }

        if (! $capabilities->requires_envelope) {
            return;
        }

        $result = $this->readiness->evaluate(
            voucher: $voucher,
            gate: $gate,
            context: [
                'requires_envelope' => true,
                ...$context,
            ],
        );

        if (! $result->ready) {
            throw VoucherRequiresSettlementEnvelope::forVoucher(
                voucher: $voucher,
                readinessOrCapabilities: $result,
            );
        }
    }
}
