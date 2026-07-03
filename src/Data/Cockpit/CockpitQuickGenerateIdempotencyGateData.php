<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitQuickGenerateIdempotencyGateData extends Data
{
    /**
     * @param  array<int, CockpitQuickGenerateIdempotencyGateCheckData>  $checks
     * @param  array<string, mixed>  $redactions
     */
    public function __construct(
        public readonly string $status = 'not_wired',
        public readonly array $checks = [],
        public readonly array $redactions = ['payloads' => 'not-loaded'],
    ) {}
}
