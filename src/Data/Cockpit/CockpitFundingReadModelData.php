<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitFundingReadModelData extends Data
{
    /**
     * @param  array<string, int|string>  $summary
     * @param  array<int, array<string, mixed>>  $providers
     * @param  array<int, array<string, mixed>>  $intents
     * @param  array<int, array<string, mixed>>  $suspense_cases
     * @param  array<int, array<string, mixed>>  $recovery_holds
     * @param  array<int, array<string, mixed>>  $treasury_positions
     * @param  array<string, bool|string>  $controls
     * @param  array<string, bool|string>  $redactions
     */
    public function __construct(
        public readonly string $schema = 'x-change.cockpit.funding-read-model.v1',
        public readonly string $status = 'available',
        public readonly bool $authorized = true,
        public readonly bool $read_only = true,
        public readonly array $summary = [],
        public readonly array $providers = [],
        public readonly array $intents = [],
        public readonly array $suspense_cases = [],
        public readonly array $recovery_holds = [],
        public readonly array $treasury_positions = [],
        public readonly array $controls = [],
        public readonly array $redactions = [],
    ) {}
}
