<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Settlement;

use Spatie\LaravelData\Data;

class SettlementEnvelopeProfileData extends Data
{
    /**
     * @param  array<string, mixed>  $driver_config
     * @param  array<int, string>  $required_payload_fields
     * @param  array<string, mixed>  $document_types
     * @param  array<string, mixed>  $checklist_items
     * @param  array<int, string>  $gate_conditions
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public bool $requires_envelope,
        public string $driver = 'philhealth-bst',
        public string $gate = 'settleable',
        public ?string $envelope_id = null,
        public ?string $flow_type = null,
        public array $driver_config = [],
        public array $required_payload_fields = [],
        public array $document_types = [],
        public array $checklist_items = [],
        public array $gate_conditions = [],
        public array $meta = [],
    ) {}

    public static function notRequired(array $meta = []): self
    {
        return new self(
            requires_envelope: false,
            driver: 'none',
            gate: 'settleable',
            meta: $meta,
        );
    }
}
