<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\PublicIssuance;

use Spatie\LaravelData\Data;

final class PublicIssuanceEstimateData extends Data
{
    /**
     * @param  array<string, float|int>  $components
     */
    public function __construct(
        public string $schema,
        public string $currency,
        public int $principal_minor,
        public int $service_fees_minor,
        public int $total_required_minor,
        public string $billing_mode,
        public array $components,
        public bool $authoritative,
        public bool $creates_order,
        public ?string $commercial_quote_reference = null,
        public ?string $commercial_offering_reference = null,
        public ?int $commercial_offering_version = null,
        public ?string $commercial_offering_snapshot_hash = null,
    ) {}
}
