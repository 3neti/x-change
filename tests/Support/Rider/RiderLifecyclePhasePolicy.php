<?php

namespace LBHurtado\XChange\Tests\Support\Rider;

use LBHurtado\XRider\Data\RiderStageData;

class RiderLifecyclePhasePolicy
{
    public const CLAIM_PREVIEW_PHASES = [
        'pre_claim',
        'runtime',
    ];

    public const SUCCESS_PHASES = [
        'success',
        'post_claim',
        'redirect',
    ];

    public static function phaseOf(RiderStageData $stage): ?string
    {
        return $stage->phase ?? $stage->payload['phase'] ?? null;
    }

    /**
     * @param array<int, RiderStageData> $stages
     * @param array<int, string> $allowedPhases
     * @return array<int, RiderStageData>
     */
    public static function stagesInPhases(array $stages, array $allowedPhases): array
    {
        return collect($stages)
            ->filter(fn (RiderStageData $stage): bool =>
            in_array(self::phaseOf($stage), $allowedPhases, true)
            )
            ->values()
            ->all();
    }

    /**
     * @param array<int, RiderStageData> $stages
     * @return array<int, RiderStageData>
     */
    public static function claimPreviewStages(array $stages): array
    {
        return self::stagesInPhases($stages, self::CLAIM_PREVIEW_PHASES);
    }

    /**
     * @param array<int, RiderStageData> $stages
     * @return array<int, RiderStageData>
     */
    public static function successStages(array $stages): array
    {
        return self::stagesInPhases($stages, self::SUCCESS_PHASES);
    }

    /**
     * @param array<int, RiderStageData> $stages
     * @return array<int, string|null>
     */
    public static function keys(array $stages): array
    {
        return collect($stages)
            ->map(fn (RiderStageData $stage) => $stage->key)
            ->values()
            ->all();
    }
}
