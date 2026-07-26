<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Payment;

use Carbon\CarbonImmutable;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionMoneyData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

final readonly class VoucherCollectionJournal
{
    public function __construct(
        private ExecutionJournalRecorder $recorder,
    ) {}

    public function record(VoucherCollection $collection): void
    {
        $authority = (array) data_get(
            $collection->meta,
            'authority',
            [],
        );
        $eventType = $collection->execution_driver
            === 'x_change_account_funding'
                ? 'account_funding.pay_code.paid'
                : 'voucher.collection.completed';

        $this->recorder->record(new ExecutionJournalEntryData(
            eventType: $eventType,
            occurredAt: CarbonImmutable::parse(
                $collection->completed_at ?? $collection->created_at,
            ),
            actor: new ExecutionActorData(
                id: (string) ($authority['reference'] ?? ''),
                type: (string) ($authority['type'] ?? 'collection_authority'),
            ),
            subject: new ExecutionSubjectData(
                id: (string) $collection->voucher_id,
                type: 'voucher',
                display: 'Pay Code collection',
            ),
            references: new ExecutionReferenceData(
                correlationId: 'voucher-collection:'.$collection->getKey(),
                causationId: (string) ($authority['reference'] ?? ''),
                executionId: (string) $collection->getKey(),
                externalReference: $collection->treasury_operation_reference
                    ?? $collection->provider_transaction_id,
                metadata: [
                    'voucher_collection_id' => (string) $collection->getKey(),
                    'execution_driver' => $collection->execution_driver,
                    'treasury_operation_reference' => $collection->treasury_operation_reference,
                    'provider_transaction_id' => $collection->provider_transaction_id,
                ],
            ),
            idempotencyKey: 'x-change:voucher-collection:completed:'
                .$collection->getKey(),
            payload: [
                'status' => $collection->status,
                'collection_number' => $collection->collection_number,
                'execution_driver' => $collection->execution_driver,
                'provider' => $collection->provider,
                'provider_calls' => (bool) data_get(
                    $collection->meta,
                    'posting.provider_calls',
                    $collection->execution_driver === 'provider_wallet',
                ),
                'provider_inventory_changed' => (bool) data_get(
                    $collection->meta,
                    'posting.provider_inventory_changed',
                    false,
                ),
            ],
            money: new ExecutionMoneyData(
                currency: $collection->currency,
                minorAmount: $collection->collected_amount_minor,
            ),
            metadata: [
                'schema' => 'x-change.voucher-collection-journal.v1',
                'domain' => $collection->execution_driver
                    === 'x_change_account_funding'
                        ? 'account_funding'
                        : 'voucher_collection',
                'source' => 'persisted_voucher_collection',
                'accounting_authority' => $collection->treasury_operation_reference === null
                    ? 'provider_confirmed_wallet_posting'
                    : 'treasury_position_operation',
            ],
        ));
    }
}
