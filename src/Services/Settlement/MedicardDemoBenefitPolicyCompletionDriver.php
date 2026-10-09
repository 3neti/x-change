<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use LBHurtado\XChange\Contracts\CampaignPolicyCompletionDriverContract;
use LBHurtado\XChange\Data\Settlement\PolicyCompletionPreparationData;
use LBHurtado\XChange\Models\CompletionClaimEvidenceProjection;

final readonly class MedicardDemoBenefitPolicyCompletionDriver implements CampaignPolicyCompletionDriverContract
{
    public const DRIVER_ID = 'medicard.demo-benefit';

    public const DRIVER_VERSION = '1.0.0';

    public function __construct(
        private BuildCampaignPolicyCompletionPreparation $preparations,
    ) {}

    public function driverId(): string
    {
        return self::DRIVER_ID;
    }

    public function driverVersion(): string
    {
        return self::DRIVER_VERSION;
    }

    public function prepare(
        CompletionClaimEvidenceProjection $projection,
    ): PolicyCompletionPreparationData {
        return $this->preparations->handle(
            projection: $projection,
            driverId: self::DRIVER_ID,
            driverVersion: self::DRIVER_VERSION,
            productLabel: 'Medicard demo benefit',
            idempotencyPrefix: 'medicard-demo-benefit:',
        );
    }
}
