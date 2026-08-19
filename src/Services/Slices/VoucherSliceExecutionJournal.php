<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Slices;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Models\VoucherSliceExecutionOutbox;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionMoneyData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use Throwable;

final readonly class VoucherSliceExecutionJournal
{
    public function __construct(private ExecutionJournalRecorder $recorder)
    {
    }

    public function deliverForExecution(int $executionId): void
    {
        VoucherSliceExecutionOutbox::query()
            ->where('execution_id', $executionId)
            ->where('status', 'pending')
            ->orderBy('id')
            ->each(fn (VoucherSliceExecutionOutbox $event) => $this->deliver($event));
    }

    public function deliverPending(int $limit = 100): int
    {
        $events = VoucherSliceExecutionOutbox::query()
            ->where('status', 'pending')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();

        $events->each(fn (VoucherSliceExecutionOutbox $event) => $this->deliver($event));

        return $events->count();
    }

    private function deliver(VoucherSliceExecutionOutbox $event): void
    {
        $payload = (array) $event->payload;

        try {
            $this->recorder->record(new ExecutionJournalEntryData(
                eventType: $event->event_type,
                occurredAt: CarbonImmutable::parse($event->occurred_at),
                actor: new ExecutionActorData(id: 'slice-execution-engine', type: 'system'),
                subject: new ExecutionSubjectData(
                    id: (string) ($payload['voucher_reference'] ?? 'unknown'),
                    type: 'voucher',
                    display: 'Pay Code',
                ),
                references: new ExecutionReferenceData(
                    correlationId: (string) ($payload['execution_reference'] ?? $event->reference),
                    causationId: (string) ($payload['plan_fingerprint'] ?? ''),
                    executionId: (string) ($payload['execution_reference'] ?? $event->reference),
                ),
                idempotencyKey: 'x-change:slice-outbox:'.$event->reference,
                payload: [
                    'status' => $payload['status'] ?? null,
                    'slice_ids' => $payload['slice_ids'] ?? [],
                    'claim_number' => $payload['claim_number'] ?? null,
                    'provider_calls' => $payload['provider_calls'] ?? null,
                ],
                money: new ExecutionMoneyData(
                    currency: (string) ($payload['currency'] ?? 'PHP'),
                    minorAmount: (int) ($payload['amount_minor'] ?? 0),
                ),
                metadata: [
                    'schema' => 'x-change.voucher-slice-execution-journal.v1',
                    'source' => 'voucher_slice_execution_outbox',
                    'plan_fingerprint' => $payload['plan_fingerprint'] ?? null,
                ],
            ));

            DB::transaction(function () use ($event): void {
                $locked = VoucherSliceExecutionOutbox::query()->lockForUpdate()->find($event->getKey());

                if ($locked === null || $locked->status === 'delivered') {
                    return;
                }

                $locked->forceFill([
                    'status' => 'delivered',
                    'attempts' => $locked->attempts + 1,
                    'delivered_at' => now(),
                    'last_error' => null,
                ])->save();
            }, attempts: 5);
        } catch (Throwable $exception) {
            report($exception);

            VoucherSliceExecutionOutbox::query()
                ->whereKey($event->getKey())
                ->update([
                    'attempts' => $event->attempts + 1,
                    'last_error' => mb_substr($exception::class, 0, 500),
                    'updated_at' => now(),
                ]);
        }
    }
}
