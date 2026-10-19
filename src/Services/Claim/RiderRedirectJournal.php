<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Models\RiderRedirectEvent;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

final class RiderRedirectJournal
{
    public function deliver(int $limit = 100): int
    {
        $count = 0;
        $ids = RiderRedirectEvent::query()->whereNull('delivered_at')->orderBy('id')->limit($limit)->pluck('id');
        foreach ($ids as $id) {
            try {
                DB::transaction(function () use ($id, &$count): void {
                    $event = RiderRedirectEvent::query()->lockForUpdate()->findOrFail($id);
                    if ($event->delivered_at !== null) {
                        return;
                    }
                    app(ExecutionJournalRecorder::class)->record(new ExecutionJournalEntryData(
                        eventType: $event->event_type,
                        occurredAt: $event->occurred_at,
                        actor: new ExecutionActorData(id: 'x-change', type: 'system'),
                        subject: new ExecutionSubjectData(id: $event->payload['obligation_reference'], type: 'rider_redirect'),
                        references: new ExecutionReferenceData(correlationId: $event->payload['obligation_reference'], externalReference: $event->attempt_reference, metadata: ['voucher_id' => $event->payload['voucher_id']]),
                        idempotencyKey: $event->reference,
                        payload: $event->payload,
                        metadata: ['domain' => 'claim', 'source' => 'x-change.rider-redirect'],
                    ));
                    $event->update(['delivered_at' => now(), 'delivery_attempts' => $event->delivery_attempts + 1, 'last_error_type' => null]);
                    $count++;
                });
            } catch (\Throwable $exception) {
                RiderRedirectEvent::query()->whereKey($id)->increment('delivery_attempts', 1, ['last_error_type' => $exception::class]);
                throw $exception;
            }
        }

        return $count;
    }
}
