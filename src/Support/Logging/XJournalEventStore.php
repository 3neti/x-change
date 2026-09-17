<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Logging;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use LBHurtado\XChange\Contracts\AppendableEventStoreContract;
use LBHurtado\XChange\Contracts\EventStoreContract;
use LBHurtado\XChange\Contracts\PaginatedEventStoreContract;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use LBHurtado\XJournal\Services\JournalEntryRetriever;
use Throwable;

class XJournalEventStore implements AppendableEventStoreContract, EventStoreContract, PaginatedEventStoreContract
{
    protected const SOURCE_SYSTEM = 'x-change';

    public function __construct(
        protected ExecutionJournalRecorder $recorder,
        protected JournalEntryRetriever $retriever,
        protected CacheEventStore $projection,
    ) {}

    public function list(array $filters = []): array
    {
        return $this->page($filters)['items'];
    }

    public function page(array $filters = []): array
    {
        $page = $this->retriever->sourcePage(
            sourceSystem: self::SOURCE_SYSTEM,
            limit: $this->listLimit($filters['limit'] ?? null),
            beforeId: $this->decodeCursor($filters['cursor'] ?? null),
            eventType: $this->filterString($filters['type'] ?? null),
            subjectType: $this->filterString($filters['resource_type'] ?? null),
        );

        return [
            'items' => $page['entries']
                ->map(fn (ExecutionJournalEntry $entry): array => $this->toEvent($entry))
                ->all(),
            'next_cursor' => $this->encodeCursor($page['next_cursor']),
            'has_more' => $page['has_more'],
        ];
    }

    public function find(string $id): ?array
    {
        $entry = $this->retriever->findBySourceIdentity(self::SOURCE_SYSTEM, $id);

        return $entry instanceof ExecutionJournalEntry ? $this->toEvent($entry) : null;
    }

    public function append(array $event): void
    {
        $id = $this->requiredEventId($event);
        $entry = $this->recorder->record($this->toJournalEntry($id, $event));

        if (! config('x-change.audit.cache.projection_enabled', true)) {
            return;
        }

        try {
            $this->projection->append($this->toEvent($entry));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $event
     */
    protected function toJournalEntry(string $id, array $event): ExecutionJournalEntryData
    {
        $actor = $this->filterString($event['actor'] ?? null);
        $resourceType = $this->filterString($event['resource_type'] ?? null) ?? 'audit_event';
        $resourceId = isset($event['resource_id']) ? (string) $event['resource_id'] : $id;
        $occurredAt = $this->filterString($event['occurred_at'] ?? null);

        return new ExecutionJournalEntryData(
            eventType: $this->filterString($event['type'] ?? null) ?? 'event.unknown',
            occurredAt: $occurredAt !== null ? CarbonImmutable::parse($occurredAt) : CarbonImmutable::now(),
            actor: new ExecutionActorData(
                id: $actor,
                type: $actor !== null ? 'audit_actor' : 'system',
                name: $actor,
            ),
            subject: new ExecutionSubjectData(id: $resourceId, type: $resourceType),
            references: new ExecutionReferenceData(
                correlationId: $this->filterString($event['correlation_id'] ?? null),
            ),
            idempotencyKey: 'x-change:audit:'.$id,
            payload: is_array($event['payload'] ?? null) ? $event['payload'] : [],
            metadata: [
                'status' => $this->filterString($event['status'] ?? null) ?? 'recorded',
                'event_idempotency_key' => $this->filterString($event['idempotency_key'] ?? null),
            ],
            sourceSystem: self::SOURCE_SYSTEM,
            sourceEventId: $id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function toEvent(ExecutionJournalEntry $entry): array
    {
        return [
            'id' => (string) $entry->source_event_id,
            'type' => (string) $entry->event_type,
            'status' => (string) data_get($entry->metadata, 'status', 'recorded'),
            'actor' => $entry->actor_id !== null ? (string) $entry->actor_id : null,
            'resource_type' => $entry->subject_type !== null ? (string) $entry->subject_type : null,
            'resource_id' => $entry->subject_id !== null ? (string) $entry->subject_id : null,
            'correlation_id' => $entry->correlation_id !== null ? (string) $entry->correlation_id : null,
            'idempotency_key' => data_get($entry->metadata, 'event_idempotency_key'),
            'occurred_at' => $entry->occurred_at?->toIso8601String(),
            'payload' => is_array($entry->payload) ? $entry->payload : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     */
    protected function requiredEventId(array $event): string
    {
        $id = $this->filterString($event['id'] ?? null);

        return $id ?? Str::uuid()->toString();
    }

    protected function listLimit(mixed $requested): int
    {
        $default = max(1, (int) config('x-change.audit.cache.default_list_limit', 50));
        $maximum = max(1, min(200, (int) config('x-change.audit.cache.max_list_limit', 100)));

        return min($maximum, is_numeric($requested) ? max(1, (int) $requested) : $default);
    }

    protected function encodeCursor(?int $id): ?string
    {
        if ($id === null) {
            return null;
        }

        return rtrim(strtr(base64_encode((string) $id), '+/', '-_'), '=');
    }

    protected function decodeCursor(mixed $cursor): ?int
    {
        if (! is_string($cursor) || trim($cursor) === '') {
            return null;
        }

        $encoded = strtr($cursor, '-_', '+/');
        $padding = strlen($encoded) % 4;

        if ($padding !== 0) {
            $encoded .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($encoded, true);

        return is_string($decoded) && ctype_digit($decoded) && (int) $decoded > 0
            ? (int) $decoded
            : null;
    }

    protected function filterString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
