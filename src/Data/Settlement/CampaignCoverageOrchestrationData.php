<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Settlement;

final readonly class CampaignCoverageOrchestrationData
{
    public function __construct(
        public CampaignCoverageDecisionData $decision,
        public ?ProvisionalCoverageEnvelopeData $binding = null,
    ) {}

    public function bound(): bool
    {
        return $this->binding instanceof ProvisionalCoverageEnvelopeData;
    }
}
