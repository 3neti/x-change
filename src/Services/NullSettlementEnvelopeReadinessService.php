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
        return $this->evaluate($voucher);
    }

    public function evaluate(
        Voucher $voucher,
        string $gate = 'settleable',
        array $context = [],
    ): SettlementEnvelopeReadinessData {
        return SettlementEnvelopeReadinessData::notAvailable(
            required: (bool) ($context['requires_envelope'] ?? true),
            meta: [
                'voucher_id' => $voucher->getKey(),
                'voucher_code' => $voucher->code ?? null,
                'driver' => $context['driver'] ?? 'null',
                'gate' => $gate,
                'service' => static::class,
            ],
        );
    }
}
