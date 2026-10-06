<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use LBHurtado\XChange\Contracts\AppendableEventStoreContract;
use LBHurtado\XChange\Events\StandingFundingRuntimeChanged;
use LBHurtado\XChange\Models\StandingFundingRuntimeOutbox;
use Throwable;

final readonly class StandingFundingRuntimeOutboxProcessor
{
    private const array JOURNALED_EVENTS = [
        'standing_funding.runtime.mode_changed',
        'standing_funding.runtime.circuit_opened',
        'standing_funding.runtime.circuit_closed',
        'standing_funding.address.quarantined',
        'standing_funding.address.released',
        'standing_funding.sync.resource_exhausted',
        'standing_funding.sync.terminal_failure',
        'standing_funding.sync.ambiguous_detected',
        'standing_funding.sync.reconciled',
        'standing_funding.runtime.canary_completed',
        'standing_funding.recovery.command_executed',
    ];

    public function __construct(private AppendableEventStoreContract $journal) {}

    public function process(int $limit = 100): int
    {
        $events = StandingFundingRuntimeOutbox::query()
            ->where(fn ($query) => $query->where('journal_status', 'pending')->orWhere('broadcast_status', 'pending'))
            ->oldest('id')->limit(max(1, min(500, $limit)))->get();

        foreach ($events as $event) {
            $this->projectJournal($event);
            $this->projectBroadcast($event->refresh());
        }

        return $events->count();
    }

    private function projectJournal(StandingFundingRuntimeOutbox $event): void
    {
        if ($event->journal_status !== 'pending') {
            return;
        }
        if (! in_array($event->event_type, self::JOURNALED_EVENTS, true)) {
            $event->forceFill(['journal_status' => 'skipped'])->save();

            return;
        }
        try {
            $this->journal->append([
                'id' => (string) $event->reference,
                'type' => $event->event_type,
                'resource_type' => $event->aggregate_type,
                'resource_id' => $event->aggregate_id,
                'idempotency_key' => 'standing-funding:'.$event->reference,
                'occurred_at' => $event->occurred_at->toIso8601String(),
                'payload' => $event->payload,
            ]);
            $event->forceFill(['journal_status' => 'delivered', 'journaled_at' => now(), 'journal_attempts' => $event->journal_attempts + 1, 'journal_error' => null])->save();
        } catch (Throwable $failure) {
            $event->forceFill(['journal_attempts' => $event->journal_attempts + 1, 'journal_error' => class_basename($failure)])->save();
            $this->reportSafely($failure);
        }
    }

    private function projectBroadcast(StandingFundingRuntimeOutbox $event): void
    {
        if ($event->broadcast_status !== 'pending') {
            return;
        }
        try {
            StandingFundingRuntimeChanged::dispatch((string) $event->reference, $event->event_type, $event->occurred_at->toIso8601String());
            $event->forceFill(['broadcast_status' => 'delivered', 'broadcast_at' => now(), 'broadcast_attempts' => $event->broadcast_attempts + 1, 'broadcast_error' => null])->save();
        } catch (Throwable $failure) {
            $event->forceFill(['broadcast_attempts' => $event->broadcast_attempts + 1, 'broadcast_error' => class_basename($failure)])->save();
            $this->reportSafely($failure);
        }
    }

    private function reportSafely(Throwable $failure): void
    {
        try {
            report($failure);
        } catch (Throwable) {
        }
    }
}
