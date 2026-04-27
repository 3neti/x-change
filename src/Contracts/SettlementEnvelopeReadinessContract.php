<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Settlement\SettlementEnvelopeReadinessData;

interface SettlementEnvelopeReadinessContract
{
    public function check(Voucher $voucher): SettlementEnvelopeReadinessData;
}
