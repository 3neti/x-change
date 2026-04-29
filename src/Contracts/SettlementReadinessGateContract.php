<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;

interface SettlementReadinessGateContract
{
    public function assertReady(Voucher $voucher): void;
}
