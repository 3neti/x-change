<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitPayCodeListRecordData extends Data
{
    /**
     * @param  array<int, CockpitPayCodeRowActionData>  $actions
     * @param  array<int, CockpitPayCodeInstructionBadgeData>  $instruction_badges
     * @param  array<string, mixed>  $amount_presentation
     * @param  array<string, mixed>  $collection
     * @param  array<string, mixed>  $pos_reference
     */
    public function __construct(
        public readonly string $code,
        public readonly string $template,
        public readonly CockpitPayCodeCapabilityData $capability,
        public readonly array $instruction_badges,
        public readonly string|int|float|null $amount,
        public readonly array $amount_presentation,
        public readonly ?string $currency,
        public readonly string $status,
        public readonly string $display_status,
        public readonly ?string $purpose,
        public readonly CockpitPayCodePartyData $party,
        public readonly CockpitPayCodeTimingData $timing,
        public readonly CockpitPayCodeTerminalControlData $terminal_control,
        public readonly string $owner,
        public readonly ?string $last_activity,
        public readonly ?CockpitPayCodeAttentionData $attention = null,
        public readonly array $actions = [],
        public readonly ?string $consumer_status = null,
        public readonly array $collection = [],
        public readonly array $pos_reference = [],
    ) {}
}
