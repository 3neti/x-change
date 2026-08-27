<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Payment;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Contracts\VoucherCollectionWalletResolverContract;
use LBHurtado\XChange\Events\FundingProjectionChanged;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\Cockpit\CockpitPosSaleReferenceService;
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
        private VoucherCollectionWalletResolverContract $collectionWallets,
        private CockpitPosSaleReferenceService $posSaleReferences,
    ) {}

    public function record(VoucherCollection $collection): void
    {
        $authority = (array) data_get(
            $collection->meta,
            'authority',
            [],
        );
        $isSucceeded = $collection->isSucceeded();
        $eventType = match (true) {
            ! $isSucceeded => 'voucher.collection.failed',
            $collection->execution_driver === 'x_change_account_funding' => 'account_funding.pay_code.paid',
            default => 'voucher.collection.completed',
        };
        $journalStatus = $isSucceeded ? 'completed' : 'failed';
        $saleReference = $this->posSaleReferences->saleReferenceForVoucherId($collection->voucher_id);

        $this->recorder->record(new ExecutionJournalEntryData(
            eventType: $eventType,
            occurredAt: CarbonImmutable::parse(
                $collection->completed_at
                    ?? $collection->attempted_at
                    ?? $collection->created_at,
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
                    'sale_reference' => $saleReference,
                ],
            ),
            idempotencyKey: 'x-change:voucher-collection:'.$journalStatus.':'
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
                'sale_reference' => $saleReference,
            ],
            money: new ExecutionMoneyData(
                currency: $collection->currency,
                minorAmount: $isSucceeded
                    ? $collection->collected_amount_minor
                    : $collection->requested_amount_minor,
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

        if ($isSucceeded) {
            $voucher = $collection->voucher()->firstOrFail();
            $holder = $this->collectionWallets->resolve($voucher)->holder;

            if ($holder instanceof Model) {
                FundingProjectionChanged::dispatch(
                    $holder::class,
                    (string) $holder->getKey(),
                    'voucher-collection:'.$collection->getKey(),
                    CarbonImmutable::parse(
                        $collection->completed_at ?? $collection->created_at,
                    )->toIso8601String(),
                    'voucher_collection_settled',
                );
            }
        }
    }
}
