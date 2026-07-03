<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitPayCodeListReadModelData extends Data
{
    /**
     * @param  array<int, CockpitPayCodeListRecordData>  $records
     * @param  array<string, mixed>  $redactions
     */
    public function __construct(
        public readonly string $status,
        public readonly bool $authorized = false,
        public readonly ?string $query = null,
        public readonly array $records = [],
        public readonly array $redactions = ['payloads' => 'not-loaded'],
    ) {}
}
