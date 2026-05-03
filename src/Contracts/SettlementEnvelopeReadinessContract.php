<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Settlement\SettlementEnvelopeReadinessData;

interface SettlementEnvelopeReadinessContract
{
    /**
     * Legacy compatibility method.
     *
     * Existing settlement preparation code still calls check().
     * New code should prefer evaluate().
     */
    public function check(Voucher $voucher): SettlementEnvelopeReadinessData;

    public function evaluate(
        Voucher $voucher,
        string $gate = 'settleable',
        array $context = [],
    ): SettlementEnvelopeReadinessData;
}
