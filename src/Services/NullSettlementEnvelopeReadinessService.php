<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\SettlementEnvelopeReadinessContract;
use LBHurtado\XChange\Data\Settlement\SettlementEnvelopeReadinessData;

class NullSettlementEnvelopeReadinessService implements SettlementEnvelopeReadinessContract
{
    public function check(Voucher $voucher): SettlementEnvelopeReadinessData
    {
        return SettlementEnvelopeReadinessData::notAvailable(required: true);
    }
}
