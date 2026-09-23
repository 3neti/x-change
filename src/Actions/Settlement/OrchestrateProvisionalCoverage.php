<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Settlement;

use InvalidArgumentException;
use LBHurtado\XChange\Data\Settlement\CampaignCoverageOrchestrationData;
use LBHurtado\XChange\Models\CampaignPaymentRecognition;
use LBHurtado\XChange\Services\Settlement\CampaignCoverageDriverRegistry;

final readonly class OrchestrateProvisionalCoverage
{
    public function __construct(
        private CampaignCoverageDriverRegistry $drivers,
        private BindProvisionalCoverage $bind,
    ) {}

    public function handle(
        CampaignPaymentRecognition $recognition,
        string $driverId,
        string $driverVersion,
    ): CampaignCoverageOrchestrationData {
        if (! $recognition->exists) {
            throw new InvalidArgumentException('A persisted campaign payment recognition is required.');
        }

        $driver = $this->drivers->for($driverId, $driverVersion);
        $decision = $driver->decide($recognition);

        if (! $decision->eligible) {
            return new CampaignCoverageOrchestrationData($decision);
        }

        $terms = $decision->terms;

        if ($terms === null) {
            throw new InvalidArgumentException(
                'Eligible campaign coverage decision did not provide terms.',
            );
        }

        if (! hash_equals(trim($driverId), trim($terms->driverId))
            || ! hash_equals(trim($driverVersion), trim($terms->driverVersion))) {
            throw new InvalidArgumentException(
                'Campaign coverage driver returned terms for a different driver identity.',
            );
        }

        return new CampaignCoverageOrchestrationData(
            decision: $decision,
            binding: $this->bind->handle($recognition, $terms),
        );
    }
}
