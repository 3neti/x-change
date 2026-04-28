<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Exceptions;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\VoucherFlow\VoucherFlowCapabilitiesData;

class VoucherRequiresSettlementEnvelope extends VoucherFlowCapabilityException
{
    public static function forVoucher(
        Voucher $voucher,
        VoucherFlowCapabilitiesData $capabilities,
    ): self {
        return new self(
            voucher: $voucher,
            capabilities: $capabilities,
            message: "Voucher flow [{$capabilities->type->value}] requires a settlement envelope.",
        );
    }
}
