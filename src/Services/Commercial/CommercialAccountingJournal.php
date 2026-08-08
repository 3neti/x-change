<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Models\CommercialAllocation;
use LBHurtado\XChange\Models\CommercialProviderCostBatch;
use LBHurtado\XChange\Models\CommercialProviderCostSettlement;
use LBHurtado\XChange\Models\CommercialSale;
use LBHurtado\XChange\Models\PartnerCommissionPayout;
use LBHurtado\XChange\Models\PartnerCommissionPayoutBatch;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionMoneyData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

final readonly class CommercialAccountingJournal
{
    public function __construct(
        private ExecutionJournalRecorder $recorder,
    ) {}

    public function recordSalePosted(CommercialSale $sale): void
    {
        $this->recordSaleEvent(
            sale: $sale,
            eventType: 'commercial.sale.accepted',
            idempotencySuffix: 'accepted',
            occurredAt: CarbonImmutable::parse($sale->accepted_at),
            payload: [
                'status' => 'accepted',
                'quote_reference' => $sale->quote_reference,
                'catalog_reference' => $sale->catalog_reference,
                'catalog_version' => $sale->catalog_version,
                'waterfall_policy_reference' => $sale->waterfall_policy_reference,
                'waterfall_policy_version' => $sale->waterfall_policy_version,
                'snapshot_hash' => $sale->snapshot_hash,
            ],
        );
        $this->recordSaleEvent(
            sale: $sale,
            eventType: 'commercial.charge.posted',
            idempotencySuffix: 'charge-posted',
            occurredAt: CarbonImmutable::parse($sale->posted_at),
            payload: [
                'status' => 'posted',
                'source_position_reference' => $sale->source_client_funds_position_reference,
                'destination_position_reference' => $sale->commercial_clearing_position_reference,
                'treasury_operation_reference' => $sale->charge_operation_reference,
            ],
        );

        foreach ($sale->allocations as $allocation) {
            $this->recordAllocationPosted($sale, $allocation);
        }
    }

    public function recordProviderCostOutcome(
        CommercialProviderCostSettlement $settlement,
    ): ExecutionJournalEntry {
        $sale = $settlement->sale;

        return $this->recorder->record(new ExecutionJournalEntryData(
            eventType: 'commercial.provider_cost.'.$settlement->status,
            occurredAt: CarbonImmutable::parse($settlement->observed_at),
            actor: new ExecutionActorData(
                id: $settlement->provider,
                type: 'settlement_provider',
            ),
            subject: $this->subject($sale),
            references: new ExecutionReferenceData(
                correlationId: $this->correlationId($sale),
                causationId: $settlement->evidence_reference,
                executionId: (string) $settlement->getKey(),
                externalReference: $settlement->inventory_operation_reference,
                metadata: [
                    'commercial_allocation_id' => (string) $settlement->commercial_allocation_id,
                    'position_operation_reference' => $settlement->position_operation_reference,
                ],
            ),
            idempotencyKey: 'x-change:commercial:provider-cost:'.$settlement->idempotency_key,
            payload: [
                'status' => $settlement->status,
                'provider' => $settlement->provider,
                'connection_reference' => $settlement->connection_reference,
                'evidence_type' => $settlement->evidence_type,
                'cash_movement_observed' => $settlement->cash_movement_observed,
                'expected_amount_minor' => $settlement->expected_amount_minor,
                'observed_amount_minor' => $settlement->observed_amount_minor,
                'variance_amount_minor' => $settlement->variance_amount_minor,
            ],
            money: $this->money($settlement->currency, $settlement->observed_amount_minor),
            metadata: $this->metadata('provider_cost_settlement'),
        ));
    }

    public function recordProviderCostBatch(CommercialProviderCostBatch $batch): ExecutionJournalEntry
    {
        return $this->recorder->record(new ExecutionJournalEntryData(
            eventType: 'commercial.provider_cost_batch.'.$batch->status->value,
            occurredAt: CarbonImmutable::parse($batch->observed_at),
            actor: new ExecutionActorData(
                id: $batch->recorded_by_type.':'.$batch->recorded_by_id,
                type: 'commercial_provider_cost_operator',
            ),
            subject: new ExecutionSubjectData(
                id: $batch->reference,
                type: 'commercial_provider_cost_batch',
                display: 'Provider Cost Evidence Batch',
            ),
            references: new ExecutionReferenceData(
                correlationId: 'commercial-provider-cost-batch:'.$batch->reference,
                causationId: $batch->evidence_reference,
                executionId: (string) $batch->getKey(),
                externalReference: $batch->evidence_reference,
            ),
            idempotencyKey: 'x-change:commercial:provider-cost-batch:'.$batch->idempotency_key,
            payload: [
                'status' => $batch->status->value,
                'provider' => $batch->provider,
                'connection_reference' => $batch->connection_reference,
                'expected_amount_minor' => $batch->expected_amount_minor,
                'observed_amount_minor' => $batch->observed_amount_minor,
                'variance_amount_minor' => $batch->variance_amount_minor,
            ],
            money: $this->money($batch->currency, $batch->observed_amount_minor),
            metadata: $this->metadata('provider_cost_batch'),
        ));
    }

    public function recordPartnerPayoutBatch(
        PartnerCommissionPayoutBatch $batch,
        string $actorId,
        string $actorType,
    ): ExecutionJournalEntry {
        $status = $batch->status->value;

        return $this->recorder->record(new ExecutionJournalEntryData(
            eventType: 'commercial.partner_commission_batch.'.$status,
            occurredAt: CarbonImmutable::parse(
                $batch->settled_at
                    ?? $batch->rejected_at
                    ?? $batch->submitted_at
                    ?? $batch->approved_at
                    ?? $batch->requested_at,
            ),
            actor: new ExecutionActorData(id: $actorId, type: $actorType),
            subject: new ExecutionSubjectData(
                id: $batch->reference,
                type: 'partner_commission_payout_batch',
                display: 'Partner Commission Payout',
            ),
            references: new ExecutionReferenceData(
                correlationId: 'commercial-partner-commission-batch:'.$batch->reference,
                causationId: $batch->approval_reference ?? $batch->request_idempotency_key,
                executionId: (string) $batch->getKey(),
                providerReference: $batch->provider_transaction_id,
                externalReference: $batch->evidence_reference,
                metadata: [
                    'partner_reference' => $batch->partner_reference,
                    'position_operation_reference' => $batch->position_operation_reference,
                    'inventory_operation_reference' => $batch->inventory_operation_reference,
                ],
            ),
            idempotencyKey: 'x-change:commercial:partner-commission-batch:'
                .$batch->getKey().':'.$status,
            payload: [
                'status' => $status,
                'provider' => $batch->provider,
                'connection_reference' => $batch->connection_reference,
                'destination_summary' => $batch->destination_summary,
            ],
            money: $this->money($batch->currency, $batch->amount_minor),
            metadata: $this->metadata('partner_commission_payout_batch'),
        ));
    }

    public function recordPartnerPayoutRetryPrepared(
        PartnerCommissionPayoutBatch $batch,
        Model $operator,
    ): ExecutionJournalEntry {
        $nextAttempt = $batch->attempts()->count() + 1;

        return $this->recorder->record(new ExecutionJournalEntryData(
            eventType: 'commercial.partner_commission_batch.retry_prepared',
            occurredAt: CarbonImmutable::now(),
            actor: new ExecutionActorData(
                id: (string) $operator->getKey(),
                type: $operator->getMorphClass(),
            ),
            subject: new ExecutionSubjectData(
                id: $batch->reference,
                type: 'partner_commission_payout_batch',
                display: 'Partner Commission Payout',
            ),
            references: new ExecutionReferenceData(
                correlationId: 'commercial-partner-commission-batch:'.$batch->reference,
                causationId: 'destination-revision:'.$batch->commercial_partner_destination_revision_id,
                executionId: (string) $batch->getKey(),
                metadata: ['next_attempt_number' => $nextAttempt],
            ),
            idempotencyKey: 'x-change:commercial:partner-commission-batch:'
                .$batch->getKey().':retry-prepared:'.$nextAttempt,
            payload: [
                'status' => $batch->status->value,
                'destination_summary' => $batch->destination_summary,
                'destination_revision_id' => $batch->commercial_partner_destination_revision_id,
                'next_attempt_number' => $nextAttempt,
            ],
            metadata: $this->metadata('partner_commission_payout_retry'),
        ));
    }

    public function recordPartnerPayoutRequested(
        PartnerCommissionPayout $payout,
    ): ExecutionJournalEntry {
        return $this->recordPartnerPayout(
            payout: $payout,
            eventType: 'commercial.partner_commission.requested',
            actorId: $payout->maker_reference,
            actorType: 'commercial_payout_maker',
            occurredAt: CarbonImmutable::parse($payout->requested_at),
            idempotencySuffix: 'requested',
        );
    }

    public function recordPartnerPayoutApproved(
        PartnerCommissionPayout $payout,
    ): ExecutionJournalEntry {
        return $this->recordPartnerPayout(
            payout: $payout,
            eventType: 'commercial.partner_commission.approved',
            actorId: (string) $payout->checker_reference,
            actorType: 'commercial_payout_checker',
            occurredAt: CarbonImmutable::parse($payout->approved_at),
            idempotencySuffix: 'approved',
        );
    }

    public function recordPartnerPayoutSettled(
        PartnerCommissionPayout $payout,
    ): ExecutionJournalEntry {
        return $this->recordPartnerPayout(
            payout: $payout,
            eventType: 'commercial.partner_commission.settled',
            actorId: $payout->provider,
            actorType: 'settlement_provider',
            occurredAt: CarbonImmutable::parse($payout->settled_at),
            idempotencySuffix: 'settled',
        );
    }

    public function recordSaleReversed(
        CommercialSale $sale,
        string $reasonReference,
    ): ExecutionJournalEntry {
        return $this->recordSaleEvent(
            sale: $sale,
            eventType: 'commercial.sale.reversed',
            idempotencySuffix: 'reversed:'.hash('sha256', $reasonReference),
            occurredAt: CarbonImmutable::parse($sale->reversed_at),
            payload: [
                'status' => 'reversed',
                'reason_reference' => $reasonReference,
                'allocation_count' => $sale->allocations->count(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordSaleEvent(
        CommercialSale $sale,
        string $eventType,
        string $idempotencySuffix,
        CarbonImmutable $occurredAt,
        array $payload,
    ): ExecutionJournalEntry {
        return $this->recorder->record(new ExecutionJournalEntryData(
            eventType: $eventType,
            occurredAt: $occurredAt,
            actor: new ExecutionActorData(
                id: $sale->buyer_reference,
                type: 'commercial_buyer',
            ),
            subject: $this->subject($sale),
            references: new ExecutionReferenceData(
                correlationId: $this->correlationId($sale),
                causationId: $sale->acceptance_event_reference,
                executionId: (string) $sale->getKey(),
                externalReference: $sale->source_commercial_event_reference,
            ),
            idempotencyKey: 'x-change:commercial:'.$sale->reference.':'.$idempotencySuffix,
            payload: $payload,
            money: $this->money($sale->currency, $sale->total_price_minor),
            metadata: $this->metadata('commercial_sale'),
        ));
    }

    private function recordAllocationPosted(
        CommercialSale $sale,
        CommercialAllocation $allocation,
    ): ExecutionJournalEntry {
        return $this->recorder->record(new ExecutionJournalEntryData(
            eventType: 'commercial.allocation.posted',
            occurredAt: CarbonImmutable::parse($sale->posted_at),
            actor: new ExecutionActorData(
                id: 'x-change',
                type: 'commercial_waterfall',
            ),
            subject: $this->subject($sale),
            references: new ExecutionReferenceData(
                correlationId: $this->correlationId($sale),
                causationId: $sale->charge_operation_reference,
                executionId: (string) $allocation->getKey(),
                externalReference: $allocation->treasury_operation_reference,
                metadata: [
                    'policy_rule_reference' => $allocation->policy_rule_reference,
                    'destination_position_reference' => $allocation->destination_position_reference,
                ],
            ),
            idempotencyKey: 'x-change:commercial:'.$sale->reference
                .':allocation:'.$allocation->policy_rule_reference,
            payload: [
                'status' => 'posted',
                'sequence' => $allocation->sequence,
                'line_type' => $allocation->line_type,
                'category' => $allocation->category,
                'recipient_reference' => $allocation->recipient_reference,
            ],
            money: $this->money($allocation->currency, $allocation->amount_minor),
            metadata: $this->metadata('commercial_waterfall'),
        ));
    }

    private function recordPartnerPayout(
        PartnerCommissionPayout $payout,
        string $eventType,
        string $actorId,
        string $actorType,
        CarbonImmutable $occurredAt,
        string $idempotencySuffix,
    ): ExecutionJournalEntry {
        $sale = $payout->sale;

        return $this->recorder->record(new ExecutionJournalEntryData(
            eventType: $eventType,
            occurredAt: $occurredAt,
            actor: new ExecutionActorData(id: $actorId, type: $actorType),
            subject: $this->subject($sale),
            references: new ExecutionReferenceData(
                correlationId: $this->correlationId($sale),
                causationId: $payout->approval_reference ?? $payout->request_idempotency_key,
                executionId: (string) $payout->getKey(),
                externalReference: $payout->evidence_reference,
                metadata: [
                    'partner_reference' => $payout->partner_reference,
                    'commercial_allocation_id' => (string) $payout->commercial_allocation_id,
                    'position_operation_reference' => $payout->position_operation_reference,
                    'inventory_operation_reference' => $payout->inventory_operation_reference,
                ],
            ),
            idempotencyKey: 'x-change:commercial:partner-payout:'
                .$payout->getKey().':'.$idempotencySuffix,
            payload: [
                'status' => $payout->status,
                'provider' => $payout->provider,
                'connection_reference' => $payout->connection_reference,
                'maker_reference' => $payout->maker_reference,
                'checker_reference' => $payout->checker_reference,
            ],
            money: $this->money($payout->currency, $payout->amount_minor),
            metadata: $this->metadata('partner_commission_payout'),
        ));
    }

    private function subject(CommercialSale $sale): ExecutionSubjectData
    {
        return new ExecutionSubjectData(
            id: $sale->reference,
            type: 'commercial_sale',
            display: 'Commercial Sale',
        );
    }

    private function money(string $currency, int $amountMinor): ExecutionMoneyData
    {
        return new ExecutionMoneyData(
            currency: mb_strtoupper($currency),
            minorAmount: $amountMinor,
        );
    }

    private function correlationId(CommercialSale $sale): string
    {
        return 'commercial-sale:'.$sale->reference;
    }

    /**
     * @return array<string, string>
     */
    private function metadata(string $source): array
    {
        return [
            'schema' => 'x-change.commercial-accounting-journal.v1',
            'domain' => 'commercial_accounting',
            'source' => $source,
            'accounting_authority' => 'treasury_position_operations',
        ];
    }
}
