<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitFeedbackReadModelData extends Data
{
    /**
     * @param  array<int, array<string, mixed>>  $deliveries
     * @param  array<string, mixed>  $redactions
     */
    public function __construct(
        public readonly string $status,
        public readonly array $deliveries = [],
        public readonly array $redactions = ['payloads' => 'not-loaded'],
        public readonly bool $authorized = false,
    ) {}
}
