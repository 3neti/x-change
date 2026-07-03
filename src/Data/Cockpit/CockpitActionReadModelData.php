<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitActionReadModelData extends Data
{
    /**
     * @param  array<int, array<string, mixed>>  $actions
     * @param  array<int, array<string, mixed>>  $diagnostics
     * @param  array<string, mixed>  $redactions
     */
    public function __construct(
        public readonly string $status,
        public readonly array $actions = [],
        public readonly array $diagnostics = [],
        public readonly array $redactions = ['payloads' => 'not-loaded'],
        public readonly bool $authorized = false,
    ) {}
}
