<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Settlement;

use LBHurtado\SettlementEnvelope\Models\Envelope;
use LBHurtado\XChange\Models\ProvisionalCoverage;

final readonly class ProvisionalCoverageEnvelopeData
{
    public function __construct(
        public ProvisionalCoverage $coverage,
        public Envelope $envelope,
        public bool $created,
    ) {}
}
