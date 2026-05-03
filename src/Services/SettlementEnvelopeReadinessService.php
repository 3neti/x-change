<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\SettlementEnvelopeReadinessContract;
use LBHurtado\XChange\Data\Settlement\SettlementEnvelopeReadinessData;

class SettlementEnvelopeReadinessService implements SettlementEnvelopeReadinessContract
{
    public function __construct(
        protected SettlementEnvelopePreparationService $preparation,
        protected SettlementEnvelopeEvidenceExtractor $extractor,
        protected SettlementEnvelopeEvaluationEngine $engine,
    ) {}

    public function check(Voucher $voucher): SettlementEnvelopeReadinessData
    {
        return SettlementEnvelopeReadinessData::notAvailable(
            required: true,
            meta: [
                'voucher_id' => $voucher->getKey(),
                'voucher_code' => $voucher->code ?? null,
                'driver' => config('x-change.settlement.default_driver', 'philhealth-bst'),
                'gate' => config('x-change.settlement.default_gate', 'settleable'),
                'source' => 'legacy_check',
            ],
        );
    }

    public function evaluate(
        Voucher $voucher,
        string $gate = 'settleable',
        array $context = [],
    ): SettlementEnvelopeReadinessData {
        $profile = $this->preparation->prepare($voucher, $gate, $context);

        if (! $profile->requires_envelope) {
            return SettlementEnvelopeReadinessData::notRequired($profile->meta);
        }

        $evidence = $this->extractor->extract($voucher, $profile, $context);

        return $this->engine->evaluate($profile, $evidence);
    }
}
