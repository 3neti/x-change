<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\StoredValue;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LBHurtado\XChange\Contracts\AppendableEventStoreContract;
use LBHurtado\XChange\Contracts\EventStoreContract;
use LBHurtado\XChange\Models\StoredValueAuditEvent;
use LBHurtado\XChange\Models\StoredValueCollection;
use LBHurtado\XChange\Models\StoredValueHolderBinding;
use Throwable;

final readonly class StoredValueAuditJournal
{
    public function __construct(
        private AppendableEventStoreContract $writer,
        private EventStoreContract $reader,
    ) {}

    public function captureActivation(StoredValueHolderBinding $binding, int $amountMinor): StoredValueAuditEvent
    {
        return StoredValueAuditEvent::query()->firstOrCreate(
            ['event_id' => 'stored-value-activation:'.$binding->reference.':activated'],
            [
                'stored_value_holder_binding_id' => $binding->getKey(),
                'event_type' => 'stored_value.activated',
                'amount_minor' => $amountMinor,
                'balance_after_minor' => $amountMinor,
                'currency' => $binding->currency,
                'occurred_at' => $binding->activated_at,
            ],
        );
    }

    public function captureCollection(StoredValueCollection $collection): StoredValueAuditEvent
    {
        return StoredValueAuditEvent::query()->firstOrCreate(
            ['event_id' => 'stored-value-collection:'.$collection->reference.':committed'],
            [
                'stored_value_holder_binding_id' => $collection->stored_value_holder_binding_id,
                'stored_value_collection_id' => $collection->getKey(),
                'event_type' => 'stored_value.collection.committed',
                'amount_minor' => $collection->amount_minor,
                'balance_after_minor' => $collection->balance_after_minor,
                'currency' => $collection->currency,
                'occurred_at' => $collection->committed_at,
            ],
        );
    }

    public function deliverPending(int $limit = 100): int
    {
        $events = StoredValueAuditEvent::query()
            ->where('journal_status', 'pending')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();

        $events->each(fn (StoredValueAuditEvent $event) => $this->deliver($event));

        return $events->count();
    }

    public function deliverForBinding(string $reference): void
    {
        $event = StoredValueAuditEvent::query()
            ->where('event_id', 'stored-value-activation:'.$reference.':activated')
            ->first();
        if ($event instanceof StoredValueAuditEvent) {
            $this->deliver($event);
        }
    }

    public function deliverForCollection(string $reference): void
    {
        $event = StoredValueAuditEvent::query()
            ->where('event_id', 'stored-value-collection:'.$reference.':committed')
            ->first();
        if ($event instanceof StoredValueAuditEvent) {
            $this->deliver($event);
        }
    }

    public function deliver(StoredValueAuditEvent $event): void
    {
        if ($event->journal_status === 'delivered') {
            return;
        }

        try {
            if ($this->reader->find($event->event_id) === null) {
                $this->writer->append($this->payload($event));
            }

            DB::transaction(static function () use ($event): void {
                StoredValueAuditEvent::query()->whereKey($event->getKey())->update([
                    'journal_status' => 'delivered',
                    'journal_attempts' => DB::raw('journal_attempts + 1'),
                    'journaled_at' => now(),
                    'journal_error' => null,
                ]);
            });
        } catch (Throwable $exception) {
            Log::warning('Stored value audit journal delivery failed', [
                'audit_event_id' => $event->getKey(),
                'exception' => $exception::class,
            ]);
            StoredValueAuditEvent::query()->whereKey($event->getKey())->update([
                'journal_attempts' => DB::raw('journal_attempts + 1'),
                'journal_error' => mb_substr($exception::class, 0, 255),
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function payload(StoredValueAuditEvent $event): array
    {
        $binding = $event->binding;
        $collection = $event->collection;
        $isCollection = $collection instanceof StoredValueCollection;

        return [
            'id' => $event->event_id,
            'type' => $event->event_type,
            'status' => 'recorded',
            'actor' => $isCollection
                ? $collection->collector_channel.':'.$collection->collector_type.':'.$collection->collector_id
                : 'stored-value-activation-engine',
            'resource_type' => $isCollection ? 'stored_value_collection' : 'stored_value_holder_binding',
            'resource_id' => $isCollection ? $collection->reference : $binding->reference,
            'correlation_id' => $isCollection ? $collection->reference : $binding->reference,
            'idempotency_key' => $isCollection ? $collection->reference : $binding->reference,
            'occurred_at' => $event->occurred_at->toIso8601String(),
            'payload' => [
                ...($isCollection ? [
                    'channel' => $collection->collector_channel,
                    'authorization_method' => $collection->authorization_method,
                    'destination_principal_reference' => data_get($collection->receipt, 'destination_principal_reference'),
                ] : []),
                'amount_minor' => $event->amount_minor,
                'currency' => $event->currency,
                'balance_after_minor' => $event->balance_after_minor,
                ...($isCollection ? [] : ['voucher_id' => $binding->voucher_id]),
            ],
        ];
    }
}
