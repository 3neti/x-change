<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\PublicIssuance;

use Spatie\LaravelData\Data;

final class PublicIssuanceHandoffData extends Data
{
    /**
     * @param  array<string, mixed>  $estimate
     */
    public function __construct(
        public string $schema,
        public string $method,
        public string $url,
        public array $estimate,
        public bool $creates_order,
        public string $next_step,
    ) {}
}
