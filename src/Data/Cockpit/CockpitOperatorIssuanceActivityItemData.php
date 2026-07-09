<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitOperatorIssuanceActivityItemData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $code,
        public readonly string $amount,
        public readonly string $currency,
        public readonly string $status,
        public readonly string $issued_at,
        public readonly string $route,
        public readonly ?string $correlation_id = null,
        public readonly ?string $idempotency_key = null,
        public readonly ?string $operator_id = null,
        public readonly ?string $detail_href = null,
        public readonly array $metadata = [],
    ) {}
}
