<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Settlement;

use InvalidArgumentException;
use LBHurtado\XChange\Data\Settlement\PolicyCompletionPreparationData;
use LBHurtado\XChange\Models\CompletionClaimEvidenceProjection;
use LBHurtado\XChange\Services\Settlement\CampaignPolicyCompletionDriverRegistry;

final readonly class PrepareCampaignPolicyCompletion
{
    public function __construct(
        private CampaignPolicyCompletionDriverRegistry $drivers,
    ) {}

    public function handle(
        CompletionClaimEvidenceProjection $projection,
    ): PolicyCompletionPreparationData {
        if (! $projection->exists) {
            throw new InvalidArgumentException(
                'A persisted completion claim evidence projection is required.',
            );
        }

        $projection->loadMissing('issuance');
        $driver = $this->drivers->for(
            (string) $projection->issuance->driver_id,
            (string) $projection->issuance->driver_version,
        );
        $preparation = $driver->prepare($projection);

        if (! hash_equals($driver->driverId(), $preparation->driverId)
            || ! hash_equals($driver->driverVersion(), $preparation->driverVersion)) {
            throw new InvalidArgumentException(
                'Campaign policy completion driver returned a different driver identity.',
            );
        }

        return $preparation;
    }
}
