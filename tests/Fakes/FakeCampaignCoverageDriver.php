<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Tests\Fakes;

use LBHurtado\XChange\Contracts\CampaignCoverageDriverContract;
use LBHurtado\XChange\Data\Settlement\CampaignCoverageDecisionData;
use LBHurtado\XChange\Models\CampaignPaymentRecognition;
use Throwable;

final class FakeCampaignCoverageDriver implements CampaignCoverageDriverContract
{
    public int $calls = 0;

    public function __construct(
        private readonly string $id,
        private readonly string $version,
        private readonly ?CampaignCoverageDecisionData $decision = null,
        private readonly ?Throwable $failure = null,
    ) {}

    public function driverId(): string
    {
        return $this->id;
    }

    public function driverVersion(): string
    {
        return $this->version;
    }

    public function decide(
        CampaignPaymentRecognition $recognition,
    ): CampaignCoverageDecisionData {
        $this->calls++;

        if ($this->failure instanceof Throwable) {
            throw $this->failure;
        }

        return $this->decision
            ?? CampaignCoverageDecisionData::ineligible('not_configured');
    }
}
