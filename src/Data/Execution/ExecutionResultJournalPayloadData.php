<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Execution;

use Spatie\LaravelData\Data;

class ExecutionResultJournalPayloadData extends Data
{
    /**
     * @param  array{type: string, id: string|null}  $actor
     * @param  array{type: string, reference: string}  $subject
     * @param  array{execution_id: string|null, voucher_code: string, correlation_id: string|null, causation_id: string|null}  $references
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $schema = 'x-change.execution-result-journal-payload.v1',
        public readonly string $event_name = 'execution.result.recorded',
        public readonly string $domain = 'execution',
        public readonly string $idempotency_key = '',
        public readonly array $actor = [
            'type' => 'system',
            'id' => null,
        ],
        public readonly array $subject = [
            'type' => 'pay_code',
            'reference' => '',
        ],
        public readonly array $references = [
            'execution_id' => null,
            'voucher_code' => '',
            'correlation_id' => null,
            'causation_id' => null,
        ],
        public readonly array $payload = [],
        public readonly array $metadata = [],
    ) {}
}
