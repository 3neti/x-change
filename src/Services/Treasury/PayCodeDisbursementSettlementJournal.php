<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Carbon\CarbonImmutable;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionMoneyData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

final readonly class PayCodeDisbursementSettlementJournal
{
    public function __construct(
        private ExecutionJournalRecorder $recorder,
    ) {}

    public function record(
        Voucher $voucher,
        DisbursementReconciliation $reconciliation,
    ): ExecutionJournalEntry {
        $reconciliationId = (string) $reconciliation->getKey();
        $voucherId = (string) $voucher->getKey();
        $voucherCode = (string) $voucher->code;
        $reservation = (array) data_get(
            $voucher->metadata,
            'treasury.pay_code_reservation',
            [],
        );

        return $this->recorder->record(new ExecutionJournalEntryData(
            eventType: 'pay_code.disbursement.settled',
            occurredAt: CarbonImmutable::instance(
                $reconciliation->completed_at ?? now(),
            ),
            actor: new ExecutionActorData(
                id: (string) $reconciliation->provider,
                type: 'settlement_provider',
            ),
            subject: new ExecutionSubjectData(
                id: $voucherId,
                type: 'voucher',
                display: $voucherCode,
            ),
            references: new ExecutionReferenceData(
                correlationId: 'pay-code-disbursement:'.$voucherCode,
                causationId: (string) $reconciliation->provider_reference,
                executionId: $reconciliationId,
                externalReference: (string) $reconciliation->provider_transaction_id,
                metadata: [
                    'voucher_code' => $voucherCode,
                    'disbursement_reconciliation_id' => $reconciliationId,
                    'reservation_operation_reference' => data_get(
                        $reservation,
                        'operation_reference',
                    ),
                ],
            ),
            idempotencyKey: 'x-change:pay-code:disbursement-settled:'
                .$voucherId.':'.$reconciliationId,
            payload: [
                'status' => 'settled',
                'provider_status' => 'succeeded',
                'internal_status' => 'finalized',
                'provider' => (string) $reconciliation->provider,
                'settlement_rail' => (string) $reconciliation->settlement_rail,
                'provider_confirmation' => true,
                'provider_inventory_changed' => true,
            ],
            money: new ExecutionMoneyData(
                currency: mb_strtoupper((string) $reconciliation->currency),
                minorAmount: (int) round(((float) $reconciliation->amount) * 100),
            ),
            metadata: [
                'schema' => 'x-change.pay-code-disbursement-settlement-journal.v1',
                'domain' => 'pay_code',
                'source' => 'provider_disbursement_confirmation',
                'accounting_authority' => 'treasury_position_operations',
            ],
        ));
    }
}
