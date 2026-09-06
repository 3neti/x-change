<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use LBHurtado\XChange\Data\Treasury\VoucherCollectionTreasuryCorrectionData;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionMoneyData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

final readonly class VoucherCollectionTreasuryCorrectionJournal
{
    public function __construct(private ExecutionJournalRecorder $recorder) {}

    public function record(VoucherCollectionTreasuryCorrectionData $correction): void
    {
        $this->recorder->record(new ExecutionJournalEntryData(
            eventType: 'voucher.collection.treasury_corrected',
            occurredAt: now()->toImmutable(),
            actor: new ExecutionActorData(
                id: 'treasury-correction-command',
                type: 'system_operation',
            ),
            subject: new ExecutionSubjectData(
                id: (string) $correction->collectionId,
                type: 'voucher_collection',
                display: 'Voucher collection Treasury correction',
            ),
            references: new ExecutionReferenceData(
                correlationId: 'voucher-collection-correction:'.$correction->collectionId,
                causationId: 'voucher-collection:'.$correction->collectionId,
                executionId: (string) $correction->collectionId,
                externalReference: $correction->operationReferences['inventory_recognition'] ?? null,
                metadata: $correction->operationReferences,
            ),
            idempotencyKey: 'x-change:voucher-collection:treasury-corrected:'.$correction->collectionId,
            payload: [
                'status' => 'completed',
                'provider' => $correction->provider,
                'provider_calls' => false,
                'provider_inventory_changed' => true,
                'original_wallet_transaction_preserved' => true,
            ],
            money: new ExecutionMoneyData(
                currency: $correction->currency,
                minorAmount: $correction->amountMinor,
            ),
            metadata: [
                'schema' => 'x-change.voucher-collection-treasury-correction.v1',
                'domain' => 'voucher_collection',
                'source' => 'guarded_treasury_correction',
            ],
        ));
    }
}
