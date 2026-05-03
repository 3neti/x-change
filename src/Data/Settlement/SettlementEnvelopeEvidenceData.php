<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Settlement;

use Spatie\LaravelData\Data;

class SettlementEnvelopeEvidenceData extends Data
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $documents
     * @param  array<string, mixed>  $checklist
     * @param  array<string, mixed>  $claims
     * @param  array<string, mixed>  $wallet_info
     * @param  array<string, mixed>  $bio_fields
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public array $payload = [],
        public array $documents = [],
        public array $checklist = [],
        public array $claims = [],
        public array $wallet_info = [],
        public array $bio_fields = [],
        public array $meta = [],
    ) {}
}
