<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Logging;

use Illuminate\Support\Str;
use LBHurtado\XChange\Contracts\AuditLoggerContract;

class CacheAuditLogger implements AuditLoggerContract
{
    public function __construct(
        protected CacheEventStore $store,
    ) {}

    public function log(string $event, array $payload = []): void
    {
        $this->store->append([
            'id' => (string) Str::uuid(),
            'type' => $event,
            'status' => 'recorded',
            'actor' => isset($payload['actor']) && is_string($payload['actor']) ? $payload['actor'] : null,
            'resource_type' => isset($payload['resource_type']) && is_string($payload['resource_type']) ? $payload['resource_type'] : null,
            'resource_id' => isset($payload['resource_id']) ? (string) $payload['resource_id'] : null,
            'correlation_id' => isset($payload['correlation_id']) && is_string($payload['correlation_id']) ? $payload['correlation_id'] : null,
            'idempotency_key' => isset($payload['idempotency_key']) && is_string($payload['idempotency_key']) ? $payload['idempotency_key'] : null,
            'occurred_at' => now()->toIso8601String(),
            'payload' => $payload,
        ]);
    }
}
