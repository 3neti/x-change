<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use LBHurtado\XChange\Contracts\AppendableEventStoreContract;
use LBHurtado\XChange\Contracts\EventStoreContract;
use LBHurtado\XChange\Contracts\PaginatedEventStoreContract;
use LBHurtado\XChange\Support\Logging\CacheEventStore;
use LBHurtado\XChange\Support\Logging\XJournalEventStore;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use LBHurtado\XJournal\Services\JournalEntryRetriever;

function durableAuditEvent(string $id, string $type = 'funding.synced'): array
{
    return [
        'id' => $id,
        'type' => $type,
        'status' => 'recorded',
        'actor' => 'system',
        'resource_type' => 'funding_address',
        'resource_id' => 'address-1',
        'correlation_id' => 'correlation-'.$id,
        'idempotency_key' => 'request-'.$id,
        'occurred_at' => '2026-09-17T08:00:00+00:00',
        'payload' => ['result' => 'ready'],
    ];
}

it('uses x-journal as the authoritative event store', function (): void {
    $appendable = app(AppendableEventStoreContract::class);

    expect($appendable)->toBeInstanceOf(XJournalEventStore::class)
        ->and(app(EventStoreContract::class))->toBe($appendable)
        ->and(app(PaginatedEventStoreContract::class))->toBe($appendable);

    $appendable->append(durableAuditEvent('evt-durable'));
    Cache::flush();

    $event = app(EventStoreContract::class)->find('evt-durable');

    expect($event)->toMatchArray([
        'id' => 'evt-durable',
        'type' => 'funding.synced',
        'resource_type' => 'funding_address',
        'resource_id' => 'address-1',
        'idempotency_key' => 'request-evt-durable',
        'payload' => ['result' => 'ready'],
    ])->and(ExecutionJournalEntry::query()->count())->toBe(1);
});

it('replays the same source event idempotently', function (): void {
    $store = app(AppendableEventStoreContract::class);
    $event = durableAuditEvent('evt-replayed');

    $store->append($event);
    $store->append($event);

    expect(ExecutionJournalEntry::query()
        ->where('source_system', 'x-change')
        ->where('source_event_id', 'evt-replayed')
        ->count())->toBe(1);
});

it('returns stable cursor pages when newer events arrive', function (): void {
    $store = app(XJournalEventStore::class);

    foreach (range(1, 4) as $number) {
        $store->append(durableAuditEvent('evt-'.$number));
    }

    $first = $store->page(['limit' => 2]);
    $store->append(durableAuditEvent('evt-5'));
    $second = $store->page(['limit' => 2, 'cursor' => $first['next_cursor']]);

    expect(array_column($first['items'], 'id'))->toBe(['evt-4', 'evt-3'])
        ->and($first['has_more'])->toBeTrue()
        ->and($first['next_cursor'])->not->toBeNull()
        ->and(array_column($second['items'], 'id'))->toBe(['evt-2', 'evt-1'])
        ->and($second['has_more'])->toBeFalse();
});

it('keeps a durable event when the recent cache projection fails', function (): void {
    $projection = Mockery::mock(CacheEventStore::class);
    $projection->shouldReceive('append')
        ->once()
        ->andThrow(new \RuntimeException('Cache projection unavailable.'));

    $store = new XJournalEventStore(
        app(ExecutionJournalRecorder::class),
        app(JournalEntryRetriever::class),
        $projection,
    );

    $store->append(durableAuditEvent('evt-projection-failed'));

    expect($store->find('evt-projection-failed'))->not->toBeNull()
        ->and(ExecutionJournalEntry::query()
            ->where('source_event_id', 'evt-projection-failed')
            ->exists())->toBeTrue();
});
