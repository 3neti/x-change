<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\VoucherFlow\VoucherFlowCapabilitiesData;
use LBHurtado\XChange\Enums\VoucherFlowType;

interface VoucherFlowCapabilityResolverContract
{
    public function resolve(Voucher $voucher): VoucherFlowCapabilitiesData;

    public function typeOf(Voucher $voucher): VoucherFlowType;

    public function canDisburse(Voucher $voucher): bool;

    public function canCollect(Voucher $voucher): bool;

    public function canSettle(Voucher $voucher): bool;
}
