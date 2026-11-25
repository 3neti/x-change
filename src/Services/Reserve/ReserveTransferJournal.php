<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Reserve;

use Carbon\CarbonImmutable;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Models\ReserveTransfer;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionMoneyData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

final readonly class ReserveTransferJournal
{
    public function __construct(private ExecutionJournalRecorder $recorder) {}

    public function recordHold(ReserveTransfer $transfer): void
    {
        $this->recorder->record(new ExecutionJournalEntryData(
            eventType: 'reserve_transfer.hold_created', occurredAt: CarbonImmutable::instance($transfer->held_at),
            actor: new ExecutionActorData(id: 'x-change', type: 'system'),
            subject: new ExecutionSubjectData(id: (string) $transfer->getKey(), type: 'reserve_transfer', display: $transfer->reference),
            references: new ExecutionReferenceData(correlationId: 'reserve-transfer:'.$transfer->reference,
                executionId: (string) $transfer->getKey(), metadata: ['hold_operation_reference' => $transfer->hold_operation_reference]),
            idempotencyKey: 'x-change:reserve-transfer:hold-created:'.$transfer->getKey(),
            payload: ['provider' => $transfer->provider, 'source_treasury_connection' => $transfer->source_treasury_connection],
            money: new ExecutionMoneyData(currency: $transfer->currency, minorAmount: $transfer->amount_minor),
            metadata: ['schema' => 'x-change.reserve-transfer-hold.v1', 'source' => 'treasury_operations'],
        ));
    }

    public function recordFundingOrderLink(ReserveTransfer $transfer, PayCodeIssuanceFundingOrder $order): void
    {
        $this->recorder->record(new ExecutionJournalEntryData(
            eventType: 'reserve_transfer.funding_order_linked', occurredAt: CarbonImmutable::now(),
            actor: new ExecutionActorData(id: 'x-change', type: 'system'),
            subject: new ExecutionSubjectData(id: (string) $transfer->getKey(), type: 'reserve_transfer', display: $transfer->reference),
            references: new ExecutionReferenceData(correlationId: 'reserve-transfer:'.$transfer->reference,
                executionId: (string) $transfer->getKey(), metadata: ['funding_order_reference' => $order->reference]),
            idempotencyKey: 'x-change:reserve-transfer:funding-order-linked:'.$transfer->getKey(),
            payload: ['funding_order_reference' => $order->reference, 'provider' => $transfer->provider],
            money: new ExecutionMoneyData(currency: $transfer->currency, minorAmount: $transfer->amount_minor),
            metadata: ['schema' => 'x-change.reserve-transfer-funding-order-link.v1'],
        ));
    }

    public function recordPaidOrderRejection(ReserveTransfer $transfer, PayCodeIssuanceFundingOrder $order): void
    {
        $this->recorder->record(new ExecutionJournalEntryData(
            eventType: 'reserve_transfer.funded_order_rejected', occurredAt: CarbonImmutable::now(),
            actor: new ExecutionActorData(id: 'netbank', type: 'settlement_provider'),
            subject: new ExecutionSubjectData(id: (string) $transfer->getKey(), type: 'reserve_transfer', display: $transfer->reference),
            references: new ExecutionReferenceData(correlationId: 'reserve-transfer:'.$transfer->reference,
                executionId: (string) $transfer->getKey(), externalReference: $transfer->provider_transaction_id,
                metadata: ['funding_order_reference' => $order->reference]),
            idempotencyKey: 'x-change:reserve-transfer:funded-order-rejected:'.$transfer->getKey(),
            payload: ['funding_order_reference' => $order->reference, 'funds_remain_held' => true],
            money: new ExecutionMoneyData(currency: $transfer->currency, minorAmount: $transfer->amount_minor),
            metadata: ['schema' => 'x-change.reserve-transfer-funded-order-rejection.v1'],
        ));
    }

    public function recordBankSettlement(ReserveTransfer $transfer): void
    {
        $this->recorder->record(new ExecutionJournalEntryData(
            eventType: 'reserve_transfer.bank_settled',
            occurredAt: CarbonImmutable::instance($transfer->provider_settled_at),
            actor: new ExecutionActorData(id: $transfer->provider, type: 'settlement_provider'),
            subject: new ExecutionSubjectData(id: (string) $transfer->getKey(), type: 'reserve_transfer', display: $transfer->reference),
            references: new ExecutionReferenceData(
                correlationId: 'reserve-transfer:'.$transfer->reference,
                executionId: (string) $transfer->getKey(),
                externalReference: $transfer->provider_transaction_id,
            ),
            idempotencyKey: 'x-change:reserve-transfer:bank-settled:'.$transfer->getKey(),
            payload: [
                'provider' => $transfer->provider,
                'source_treasury_connection' => $transfer->source_treasury_connection,
                'reserve_treasury_connection' => $transfer->reserve_treasury_connection,
                'bank_settled' => true,
                'treasury_posted' => false,
            ],
            money: new ExecutionMoneyData(currency: $transfer->currency, minorAmount: $transfer->amount_minor),
            metadata: ['schema' => 'x-change.reserve-transfer-bank-settlement.v1', 'source' => 'provider_status_check'],
        ));
    }

    public function recordTreasuryPosting(ReserveTransfer $transfer): void
    {
        $this->recorder->record(new ExecutionJournalEntryData(
            eventType: 'reserve_transfer.treasury_posted',
            occurredAt: CarbonImmutable::instance($transfer->treasury_posted_at),
            actor: new ExecutionActorData(id: 'x-change', type: 'system'),
            subject: new ExecutionSubjectData(id: (string) $transfer->getKey(), type: 'reserve_transfer', display: $transfer->reference),
            references: new ExecutionReferenceData(correlationId: 'reserve-transfer:'.$transfer->reference,
                executionId: (string) $transfer->getKey(), externalReference: $transfer->provider_transaction_id),
            idempotencyKey: 'x-change:reserve-transfer:treasury-posted:'.$transfer->getKey(),
            payload: ['provider' => $transfer->provider, 'source_treasury_connection' => $transfer->source_treasury_connection,
                'reserve_treasury_connection' => $transfer->reserve_treasury_connection, 'bank_settled' => true, 'treasury_posted' => true],
            money: new ExecutionMoneyData(currency: $transfer->currency, minorAmount: $transfer->amount_minor),
            metadata: ['schema' => 'x-change.reserve-transfer-treasury-posting.v1', 'source' => 'treasury_operations'],
        ));
    }

    public function recordIssuance(ReserveTransfer $transfer): void
    {
        $this->recorder->record(new ExecutionJournalEntryData(
            eventType: 'reserve_transfer.pay_code_issued', occurredAt: CarbonImmutable::instance($transfer->issued_at),
            actor: new ExecutionActorData(id: 'x-change', type: 'system'),
            subject: new ExecutionSubjectData(id: (string) $transfer->getKey(), type: 'reserve_transfer', display: $transfer->reference),
            references: new ExecutionReferenceData(correlationId: 'reserve-transfer:'.$transfer->reference,
                executionId: (string) $transfer->getKey(), externalReference: $transfer->provider_transaction_id,
                metadata: ['voucher_id' => $transfer->voucher_id]),
            idempotencyKey: 'x-change:reserve-transfer:pay-code-issued:'.$transfer->getKey(),
            payload: ['provider' => $transfer->provider, 'reserve_treasury_connection' => $transfer->reserve_treasury_connection],
            money: new ExecutionMoneyData(currency: $transfer->currency, minorAmount: $transfer->amount_minor),
            metadata: ['schema' => 'x-change.reserve-transfer-issuance.v1', 'source' => 'pay_code_issuance'],
        ));
    }

    public function recordInterruptedSubmission(ReserveTransfer $transfer): void
    {
        $this->recorder->record(new ExecutionJournalEntryData(
            eventType: 'reserve_transfer.submission_uncertain', occurredAt: CarbonImmutable::now(),
            actor: new ExecutionActorData(id: 'x-change', type: 'system'),
            subject: new ExecutionSubjectData(id: (string) $transfer->getKey(), type: 'reserve_transfer', display: $transfer->reference),
            references: new ExecutionReferenceData(correlationId: 'reserve-transfer:'.$transfer->reference,
                executionId: (string) $transfer->getKey()),
            idempotencyKey: 'x-change:reserve-transfer:interrupted:'.$transfer->getKey().':'.$transfer->submitted_at->getTimestamp(),
            payload: ['provider' => $transfer->provider, 'reason' => 'submission_interrupted'],
            money: new ExecutionMoneyData(currency: $transfer->currency, minorAmount: $transfer->amount_minor),
            metadata: ['schema' => 'x-change.reserve-transfer-submission-uncertain.v1'],
        ));
    }

    public function recordHoldRelease(ReserveTransfer $transfer, string $reason): void
    {
        $this->recorder->record(new ExecutionJournalEntryData(
            eventType: 'reserve_transfer.hold_released', occurredAt: CarbonImmutable::now(),
            actor: new ExecutionActorData(id: 'x-change', type: 'system'),
            subject: new ExecutionSubjectData(id: (string) $transfer->getKey(), type: 'reserve_transfer', display: $transfer->reference),
            references: new ExecutionReferenceData(correlationId: 'reserve-transfer:'.$transfer->reference,
                executionId: (string) $transfer->getKey(), externalReference: $transfer->provider_transaction_id),
            idempotencyKey: 'x-change:reserve-transfer:hold-released:'.$transfer->getKey(),
            payload: ['provider' => $transfer->provider, 'reason' => $reason, 'source_treasury_connection' => $transfer->source_treasury_connection],
            money: new ExecutionMoneyData(currency: $transfer->currency, minorAmount: $transfer->amount_minor),
            metadata: ['schema' => 'x-change.reserve-transfer-hold-release.v1', 'source' => 'treasury_operations'],
        ));
    }
}
