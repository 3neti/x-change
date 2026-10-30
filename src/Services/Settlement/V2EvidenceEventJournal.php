<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Exceptions\VoucherRequiresSettlementEnvelope;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\V2EvidenceOutbox;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\SettlementCollectionGate;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

final readonly class V2EvidenceEventJournal
{
    public function __construct(
        private ExecutionJournalRecorder $journal,
        private V2EventWebhookDelivery $webhooks,
        private SettlementCollectionGate $collectionGate,
    ) {}

    public function attestationEvidenceCaptured(Voucher $voucher, VoucherClaim $claim): void
    {
        if (! $this->declares($voucher, 'attest')) {
            return;
        }

        $manifest = data_get($claim->meta, 'evidence.manifest_sha256');
        if ($claim->voucher_id !== $voucher->getKey() || ! is_string($manifest) || ! $claim->evidence()->exists()) {
            throw new \LogicException('Attestation evidence must be persisted for this voucher.');
        }

        $this->record($voucher, 'attestation.evidence_captured', 'voucher-claim:'.$claim->getKey(), CarbonImmutable::parse($claim->attempted_at), ['manifest_sha256' => $manifest]);
    }

    public function paymentCollected(Voucher $voucher, VoucherCollection $collection): void
    {
        if (! $this->declares($voucher, 'settle')) {
            return;
        }

        if ($collection->voucher_id !== $voucher->getKey() || ! $collection->isSucceeded() || $collection->completed_at === null) {
            throw new \LogicException('Payment evidence must be a completed collection for this voucher.');
        }

        $this->record($voucher, 'settlement.payment_collected', 'voucher-collection:'.$collection->getKey(), CarbonImmutable::parse($collection->completed_at), [
            'amount_minor' => $collection->collected_amount_minor,
            'currency' => $collection->currency,
        ]);
    }

    public function amountReviewed(Voucher $voucher, string $reference, string $manifest, int $amountMinor): void
    {
        if (! $this->declares($voucher, 'review')) {
            return;
        }

        $review = data_get($voucher->metadata, 'settlement_envelope.amount_review');
        if (! is_array($review) || ($review['reference'] ?? null) !== $reference
            || ($review['evidence_manifest_sha256'] ?? null) !== $manifest
            || ($review['amount_minor'] ?? null) !== $amountMinor) {
            throw new \LogicException('A matching persisted settlement review is required.');
        }

        $this->record($voucher, 'settlement.amount_reviewed', 'voucher-review:'.$voucher->getKey().':'.$reference,
            CarbonImmutable::parse($review['reviewed_at']), ['manifest_sha256' => $manifest, 'amount_minor' => $amountMinor]);
    }

    public function settlementReadyAfterReview(Voucher $voucher): void
    {
        if (! $this->declares($voucher, 'review')) {
            return;
        }

        $review = data_get($voucher->metadata, 'settlement_envelope.amount_review');
        if (! is_array($review) || ! is_string($review['reference'] ?? null) || empty($review['reviewed_at'])) {
            return;
        }

        $context = $this->collectionGate->contextFromVoucher($voucher);
        try {
            $this->collectionGate->ensureCollectibleSettlementIsReady($voucher);
        } catch (VoucherRequiresSettlementEnvelope) {
            return;
        }

        $this->record($voucher, 'settlement.ready', 'voucher-review:'.$voucher->getKey().':'.$review['reference'],
            CarbonImmutable::now(), ['gate' => $context['gate']]);
    }

    public function payoutConverged(DisbursementReconciliation $payout): void
    {
        $voucher = Voucher::query()->findOrFail($payout->voucher_id);
        if (! $this->declares($voucher, 'settle')) {
            return;
        }
        $event = match ($payout->internal_status) {
            'finalized' => 'settlement.payout_confirmed',
            'recovery_opened' => 'settlement.payout_failed',
            default => throw new \LogicException('A converged payout is required.'),
        };
        $this->record($voucher, $event, 'value-flow-payout:'.$payout->getKey().':attempt:'.$payout->attempt_count,
            CarbonImmutable::now(), ['amount_minor' => (int) data_get($payout->meta, 'amount_minor'), 'currency' => $payout->currency]);
    }

    private function declares(Voucher $voucher, string $event): bool
    {
        return data_get($voucher->metadata, 'instructions.execution.schema') === 'voucher.execution.v2'
            && is_array(data_get($voucher->metadata, 'instructions.execution.events.'.$event));
    }

    private function record(Voucher $voucher, string $event, string $evidence, CarbonImmutable $occurredAt, array $payload): void
    {
        $key = 'x-change:v2:'.$event.':'.$evidence;
        $outbox = V2EvidenceOutbox::query()->firstOrCreate(['event_key' => $key], [
            'voucher_id' => $voucher->getKey(), 'event_type' => $event,
            'evidence_reference' => $evidence, 'occurred_at' => $occurredAt, 'payload' => $payload,
        ]);
        if ((string) $outbox->voucher_id !== (string) $voucher->getKey() || $outbox->event_type !== $event
            || $outbox->evidence_reference !== $evidence || $outbox->payload !== $payload) {
            throw new \LogicException('V2 evidence event replay conflicts with the recorded event.');
        }
    }

    public function deliverPending(int $limit = 100): int
    {
        $events = V2EvidenceOutbox::query()->where('status', 'pending')->orderBy('id')->limit(max(1, $limit))->get();
        foreach ($events as $event) {
            if (V2EvidenceOutbox::query()->where('voucher_id', $event->voucher_id)->where('status', 'pending')->where('id', '<', $event->id)->exists()) {
                continue;
            }
            try {
                $this->deliver($event);
            } catch (\Throwable $exception) {
                V2EvidenceOutbox::query()->whereKey($event->getKey())->where('status', 'pending')->increment('attempts', 1, ['last_error' => $exception::class]);

                continue;
            }
        }

        return $events->count();
    }

    private function deliver(V2EvidenceOutbox $event): void
    {
        $this->journal->record(new ExecutionJournalEntryData(
            eventType: $event->event_type,
            occurredAt: CarbonImmutable::parse($event->occurred_at),
            actor: new ExecutionActorData(type: 'x-change'),
            subject: new ExecutionSubjectData(id: (string) $event->voucher_id, type: 'voucher'),
            references: new ExecutionReferenceData(correlationId: 'voucher:'.$event->voucher_id, causationId: $event->evidence_reference),
            idempotencyKey: $event->event_key,
            payload: $event->payload,
            metadata: ['schema' => 'x-change.v2-evidence-event.v1'],
        ));

        $this->webhooks->deliver($event);

        DB::transaction(function () use ($event): void {
            $locked = V2EvidenceOutbox::query()->lockForUpdate()->findOrFail($event->getKey());
            if ($locked->status === 'delivered') {
                return;
            }
            $locked->forceFill(['status' => 'delivered', 'attempts' => $locked->attempts + 1, 'delivered_at' => now(), 'last_error' => null])->save();
        });
    }
}
