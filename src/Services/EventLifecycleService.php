<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Contracts\EventLifecycleServiceContract;
use LBHurtado\XChange\Contracts\EventStoreContract;
use LBHurtado\XChange\Contracts\IdempotencyStoreContract;

class EventLifecycleService implements EventLifecycleServiceContract
{
    public function __construct(
        protected EventStoreContract $events,
        protected IdempotencyStoreContract $idempotency,
    ) {}

    public function list(array $filters = []): array
    {
        return $this->events->list($filters);
    }

    public function show(string $event): mixed
    {
        $record = $this->events->find($event);

        return $record ?? [
            'id' => $event,
            'type' => 'event.unknown',
            'status' => 'missing',
            'actor' => null,
            'resource_type' => null,
            'resource_id' => null,
            'correlation_id' => null,
            'idempotency_key' => null,
            'occurred_at' => null,
            'payload' => [],
        ];
    }

    public function showIdempotencyKey(string $key): mixed
    {
        $record = $this->idempotency->find($key);

        if (! is_array($record)) {
            return [
                'key' => $key,
                'replayed' => false,
                'first_seen_at' => null,
                'last_seen_at' => null,
                'request_fingerprint' => null,
                'response_status' => null,
            ];
        }

        return [
            'key' => $key,
            'replayed' => (bool) ($record['replayed'] ?? false),
            'first_seen_at' => isset($record['first_seen_at']) ? (string) $record['first_seen_at'] : null,
            'last_seen_at' => isset($record['last_seen_at']) ? (string) $record['last_seen_at'] : null,
            'request_fingerprint' => isset($record['request_fingerprint']) ? (string) $record['request_fingerprint'] : null,
            'response_status' => isset($record['response_status']) && is_numeric($record['response_status'])
                ? (int) $record['response_status']
                : null,
        ];
    }
}
