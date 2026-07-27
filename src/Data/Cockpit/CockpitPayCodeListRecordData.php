<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitPayCodeListRecordData extends Data
{
    /**
     * @param  array<int, CockpitPayCodeRowActionData>  $actions
     * @param  array<int, CockpitPayCodeInstructionBadgeData>  $instruction_badges
     */
    public function __construct(
        public readonly string $code,
        public readonly string $template,
        public readonly CockpitPayCodeCapabilityData $capability,
        public readonly array $instruction_badges,
        public readonly string|int|float|null $amount,
        public readonly ?string $currency,
        public readonly string $status,
        public readonly string $display_status,
        public readonly CockpitPayCodePartyData $party,
        public readonly CockpitPayCodeTimingData $timing,
        public readonly string $owner,
        public readonly ?string $last_activity,
        public readonly array $actions = [],
    ) {}
}
