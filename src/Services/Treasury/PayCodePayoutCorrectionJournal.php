<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\PayoutDestinationRevision;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionMoneyData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

final readonly class PayCodePayoutCorrectionJournal
{
    public function __construct(
        private ExecutionJournalRecorder $recorder,
    ) {}

    public function record(
        Voucher $voucher,
        PayoutDestinationRevision $revision,
        DisbursementReconciliation $reconciliation,
        Model $requestedBy,
    ): ExecutionJournalEntry {
        return $this->recorder->record(new ExecutionJournalEntryData(
            eventType: 'pay_code.payout_destination.revised',
            occurredAt: CarbonImmutable::instance($revision->recorded_at),
            actor: new ExecutionActorData(
                id: (string) $requestedBy->getKey(),
                type: $requestedBy->getMorphClass(),
            ),
            subject: new ExecutionSubjectData(
                id: (string) $voucher->getKey(),
                type: 'voucher',
                display: (string) $voucher->code,
            ),
            references: new ExecutionReferenceData(
                correlationId: 'pay-code-disbursement:'.(string) $voucher->code,
                causationId: (string) $revision->rejected_reconciliation_id,
                executionId: (string) $reconciliation->getKey(),
                externalReference: (string) $reconciliation->provider_reference,
                metadata: [
                    'destination_revision_reference' => $revision->reference,
                    'destination_version' => $revision->version,
                ],
            ),
            idempotencyKey: 'x-change:pay-code:payout-destination-revised:'
                .$revision->reference,
            payload: [
                'status' => 'submitted',
                'claim_preserved' => true,
                'original_attempt_preserved' => true,
                'bank_code' => $revision->bank_code,
                'account_number_masked' => $revision->account_number_masked,
                'validation_status' => $revision->validation_status,
                'provider_verified' => data_get(
                    $revision->validation_metadata,
                    'provider_verified',
                    false,
                ),
            ],
            money: new ExecutionMoneyData(
                currency: (string) $reconciliation->currency,
                minorAmount: (int) round(((float) $reconciliation->amount) * 100),
            ),
            metadata: [
                'schema' => 'x-change.pay-code-payout-destination-revised-journal.v1',
                'domain' => 'pay_code',
                'source' => 'cockpit_payout_recovery',
                'sensitive_destination_exposed' => false,
            ],
        ));
    }
}
