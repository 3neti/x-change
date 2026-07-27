<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitHeaderReadModelData extends Data
{
    /**
     * @param  array<int, CockpitDashboardMetricData>  $balances
     * @param  array<string, array<string, mixed>>  $vocabulary
     * @param  array<string, mixed>  $redactions
     */
    public function __construct(
        public readonly string $schema = 'x-change.cockpit.header-read-model.v2',
        public readonly string $status = 'available',
        public readonly bool $authorized = true,
        public readonly bool $read_only = true,
        public readonly string $operating_identity = 'Account holder',
        public readonly array $balances = [],
        public readonly array $vocabulary = [],
        public readonly array $redactions = ['payloads' => 'balance-summary-only'],
    ) {}
}
