<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Settlement;

use Spatie\LaravelData\Data;

class SettlementEnvelopeReadinessData extends Data
{
    /**
     * @param  array<int, string>  $satisfied
     * @param  array<int, string>  $missing
     * @param  array<int, string>  $failed
     * @param  array<int, string>  $warnings
     * @param  array<string, mixed>  $checklist
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $documents
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public bool $required,
        public bool $exists,
        public bool $ready,
        public string $driver = 'null',
        public string $gate = 'settleable',
        public array $satisfied = [],
        public array $missing = [],
        public array $failed = [],
        public array $warnings = [],
        public array $checklist = [],
        public array $payload = [],
        public array $documents = [],
        public array $meta = [],
    ) {}

    public static function notRequired(array $meta = []): self
    {
        return new self(
            required: false,
            exists: false,
            ready: true,
            driver: 'none',
            gate: 'settleable',
            meta: $meta,
        );
    }

    public static function notAvailable(bool $required = true, array $meta = []): self
    {
        return new self(
            required: $required,
            exists: false,
            ready: ! $required,
            driver: (string) ($meta['driver'] ?? 'null'),
            gate: (string) ($meta['gate'] ?? 'settleable'),
            missing: $required ? ['settlement_envelope'] : [],
            meta: $meta,
        );
    }

    public function toExceptionContext(): array
    {
        return [
            'required' => $this->required,
            'exists' => $this->exists,
            'ready' => $this->ready,
            'driver' => $this->driver,
            'gate' => $this->gate,
            'satisfied' => $this->satisfied,
            'missing' => $this->missing,
            'failed' => $this->failed,
            'warnings' => $this->warnings,
            'checklist' => $this->checklist,
            'payload' => $this->payload,
            'documents' => $this->documents,
            'meta' => $this->meta,
        ];
    }
}
