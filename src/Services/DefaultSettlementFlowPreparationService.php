<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\SettlementFlowPreparationContract;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Data\Settlement\PrepareSettlementResultData;
use RuntimeException;

class DefaultSettlementFlowPreparationService implements SettlementFlowPreparationContract
{
    public function __construct(
        protected VoucherFlowCapabilityResolverContract $flowResolver,
    ) {}

    public function prepare(Voucher $voucher): PrepareSettlementResultData
    {
        $capabilities = $this->flowResolver->resolve($voucher);

        if (! $capabilities->type->isSettlement()) {
            throw new RuntimeException(
                "Voucher flow [{$capabilities->type->value}] cannot prepare settlement flow."
            );
        }

        return new PrepareSettlementResultData(
            voucher_code: (string) $voucher->code,
            can_start: false,
            entry_route: 'settle',
            requires_envelope: $capabilities->requires_envelope,
            requirements: [
                'envelope' => $capabilities->requires_envelope,
            ],
            capabilities: [
                'can_disburse' => $capabilities->can_disburse,
                'can_collect' => $capabilities->can_collect,
                'can_settle' => $capabilities->can_settle,
            ],
            messages: [
                'Settlement preparation is not yet implemented.',
            ],
        );
    }
}
