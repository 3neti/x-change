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

final readonly class PayCodeDisbursementRejectionJournal
{
    public function __construct(
        private ExecutionJournalRecorder $recorder,
    ) {}

    public function record(
        Voucher $voucher,
        DisbursementReconciliation $reconciliation,
        string $recoveryOperationReference,
    ): ExecutionJournalEntry {
        $voucherCode = (string) $voucher->code;
        $reconciliationId = (string) $reconciliation->getKey();

        return $this->recorder->record(new ExecutionJournalEntryData(
            eventType: 'pay_code.disbursement.rejected',
            occurredAt: CarbonImmutable::instance(
                $reconciliation->completed_at ?? now(),
            ),
            actor: new ExecutionActorData(
                id: (string) $reconciliation->provider,
                type: 'settlement_provider',
            ),
            subject: new ExecutionSubjectData(
                id: (string) $voucher->getKey(),
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
                    'recovery_operation_reference' => $recoveryOperationReference,
                ],
            ),
            idempotencyKey: 'x-change:pay-code:disbursement-rejected:'
                .$voucher->getKey().':'.$reconciliationId,
            payload: [
                'status' => 'rejected',
                'provider_status' => 'failed',
                'internal_status' => 'recovery_opened',
                'provider' => (string) $reconciliation->provider,
                'settlement_rail' => (string) $reconciliation->settlement_rail,
                'provider_inventory_changed' => false,
                'principal_returned_to_issuer' => false,
                'rejection_reason' => $reconciliation->error_message,
            ],
            money: new ExecutionMoneyData(
                currency: mb_strtoupper((string) $reconciliation->currency),
                minorAmount: (int) round(((float) $reconciliation->amount) * 100),
            ),
            metadata: [
                'schema' => 'x-change.pay-code-disbursement-rejection-journal.v1',
                'domain' => 'pay_code',
                'source' => 'provider_disbursement_reconciliation',
                'accounting_authority' => 'treasury_position_operations',
            ],
        ));
    }
}
