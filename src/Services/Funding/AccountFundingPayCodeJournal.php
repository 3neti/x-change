<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use LBHurtado\XChange\Models\SystemAccountFundingPayCodeIssuance;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Models\VoucherClaimOutcomeSelection;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionMoneyData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Data\JournalTimestampPrecisionProofData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

final readonly class AccountFundingPayCodeJournal
{
    public function __construct(
        private ExecutionJournalRecorder $recorder,
    ) {}

    public function recordIssued(
        SystemAccountFundingPayCodeIssuance $issuance,
    ): void {
        $voucherId = (string) $issuance->voucher_id;

        $this->recorder->record(new ExecutionJournalEntryData(
            eventType: 'account_funding.pay_code.issued',
            occurredAt: CarbonImmutable::parse(
                $issuance->issued_at ?? $issuance->created_at,
            ),
            actor: new ExecutionActorData(
                id: (string) $issuance->issuer_id,
                type: (string) $issuance->issuer_type,
            ),
            subject: new ExecutionSubjectData(
                id: $voucherId,
                type: 'voucher',
                display: 'Account Funding Pay Code',
            ),
            references: new ExecutionReferenceData(
                correlationId: (string) $issuance->reference,
                causationId: $issuance->evidence_reference,
                executionId: $voucherId,
                externalReference: $issuance->reservation_operation_reference,
                metadata: [
                    'issuance_reference' => $issuance->reference,
                    'reservation_operation_reference' => $issuance->reservation_operation_reference,
                ],
            ),
            idempotencyKey: 'x-change:account-funding-pay-code:issued:'.$issuance->reference,
            payload: [
                'status' => $issuance->status,
                'source' => $issuance->source,
                'provider' => $issuance->provider_code,
                'connection_reference' => $issuance->connection_reference,
                'recipient_type' => $issuance->recipient_type,
                'recipient_id' => $issuance->recipient_id,
                'bearer' => $issuance->bearer,
                'reservation_operation_reference' => $issuance->reservation_operation_reference,
                'expires_at' => $issuance->expires_at->toIso8601String(),
            ],
            money: new ExecutionMoneyData(
                currency: $issuance->currency,
                minorAmount: $issuance->amount_minor,
            ),
            metadata: $this->metadata('system_account_funding_pay_code_issuance'),
        ));
    }

    public function recordInspected(
        int|string $voucherId,
        Authenticatable $actor,
        string $inspectionToken,
    ): void {
        $tokenFingerprint = hash('sha256', $inspectionToken);

        $this->recorder->record(new ExecutionJournalEntryData(
            eventType: 'account_funding.pay_code.inspected',
            occurredAt: CarbonImmutable::now(),
            actor: new ExecutionActorData(
                id: (string) $actor->getAuthIdentifier(),
                type: $actor::class,
            ),
            subject: new ExecutionSubjectData(
                id: (string) $voucherId,
                type: 'voucher',
                display: 'Account Funding Pay Code',
            ),
            references: new ExecutionReferenceData(
                correlationId: 'account-funding-inspection:'.$tokenFingerprint,
                executionId: (string) $voucherId,
            ),
            idempotencyKey: 'x-change:account-funding-pay-code:inspected:'.$tokenFingerprint,
            payload: [
                'status' => 'eligible',
                'inspection_token_persisted' => false,
                'raw_pay_code_persisted' => false,
            ],
            metadata: $this->metadata('cockpit_account_funding_pay_code_inspection'),
        ));
    }

    public function recordOutcomeSelected(
        VoucherClaimOutcomeSelection $selection,
    ): void {
        $this->recorder->record(new ExecutionJournalEntryData(
            eventType: 'account_funding.pay_code.outcome_selected',
            occurredAt: CarbonImmutable::parse($selection->selected_at),
            actor: new ExecutionActorData(
                id: $selection->claimant_id === null
                    ? null
                    : (string) $selection->claimant_id,
                type: $selection->claimant_type,
            ),
            subject: new ExecutionSubjectData(
                id: (string) $selection->voucher_id,
                type: 'voucher',
                display: 'Account Funding Pay Code',
            ),
            references: new ExecutionReferenceData(
                correlationId: 'account-funding-claim:'.$selection->voucher_id,
                executionId: (string) $selection->voucher_id,
                metadata: [
                    'selection_id' => (string) $selection->getKey(),
                ],
            ),
            idempotencyKey: 'x-change:account-funding-pay-code:outcome-selected:'.$selection->getKey(),
            payload: [
                'outcome' => $selection->outcome_key,
                'policy_profile' => $selection->policy_profile,
                'selection_mode' => $selection->selection_mode,
            ],
            metadata: $this->metadata('voucher_claim_outcome_selection'),
        ));
    }

    public function recordApplied(VoucherClaim $claim): void
    {
        $this->recorder->record(new ExecutionJournalEntryData(
            eventType: 'account_funding.pay_code.applied',
            occurredAt: CarbonImmutable::parse(
                $claim->completed_at ?? $claim->created_at,
            ),
            actor: new ExecutionActorData(
                id: $claim->claimant_id === null
                    ? null
                    : (string) $claim->claimant_id,
                type: $claim->claimant_type,
            ),
            subject: new ExecutionSubjectData(
                id: (string) $claim->voucher_id,
                type: 'voucher',
                display: 'Account Funding Pay Code',
            ),
            references: new ExecutionReferenceData(
                correlationId: 'account-funding-claim:'.$claim->voucher_id,
                causationId: data_get(
                    $claim->meta,
                    'reservation_operation_reference',
                ),
                executionId: (string) $claim->voucher_id,
                externalReference: $claim->treasury_operation_reference,
                metadata: [
                    'claim_id' => (string) $claim->getKey(),
                    'claim_reference' => $claim->reference,
                    'treasury_operation_reference' => $claim->treasury_operation_reference,
                ],
            ),
            idempotencyKey: 'x-change:account-funding-pay-code:applied:'.$claim->getKey(),
            payload: [
                'status' => $claim->status,
                'settlement_mode' => $claim->settlement_mode,
                'claim_number' => $claim->claim_number,
                'remaining_balance_minor' => $claim->remaining_balance_minor,
                'provider_calls' => (bool) data_get(
                    $claim->meta,
                    'provider_calls',
                    false,
                ),
                'provider_inventory_changed' => (bool) data_get(
                    $claim->meta,
                    'provider_inventory_changed',
                    false,
                ),
            ],
            money: new ExecutionMoneyData(
                currency: $claim->currency,
                minorAmount: $claim->disbursed_amount_minor,
            ),
            metadata: $this->metadata('voucher_claim'),
        ));
    }

    public function recordTimestampPrecisionAttestation(
        ExecutionJournalEntry $original,
        JournalTimestampPrecisionProofData $proof,
        string $authorizationReference,
    ): ExecutionJournalEntry {
        $originalHash = (string) data_get(
            $original->integrity,
            'hash',
        );

        return $this->recorder->record(new ExecutionJournalEntryData(
            eventType: 'account_funding.pay_code.integrity_exception_attested',
            occurredAt: CarbonImmutable::now(),
            actor: new ExecutionActorData(
                id: null,
                type: 'system_control',
            ),
            subject: new ExecutionSubjectData(
                id: (string) data_get($original->subject, 'id'),
                type: 'voucher',
                display: 'Account Funding Pay Code',
            ),
            references: new ExecutionReferenceData(
                correlationId: 'account-funding-integrity-attestation:'
                    .$original->getKey(),
                causationId: (string) $original->reference_number,
                executionId: (string) data_get(
                    $original->references,
                    'execution_id',
                ),
                externalReference: $originalHash,
                metadata: [
                    'original_entry_id' => (string) $original->getKey(),
                    'original_reference_number' => (string) $original->reference_number,
                    'authorization_reference' => $authorizationReference,
                ],
            ),
            idempotencyKey: 'x-change:account-funding-pay-code:integrity-exception:'
                .hash(
                    'sha256',
                    $original->reference_number.':'.$originalHash,
                ),
            payload: [
                'status' => 'attested_legacy_exception',
                'classification' => 'timestamp_precision_loss',
                'issue_code' => 'hash_mismatch',
                'persisted_occurred_at' => $original->occurred_at->toJSON(),
                'recovered_hash_basis_occurred_at' => $proof->recoveredOccurredAt,
                'recovered_microseconds' => $proof->recoveredMicroseconds,
                'candidate_count' => $proof->candidateCount,
                'integrity_hash_reproduced' => true,
                'idempotency_fingerprint_reproduced' => $proof->idempotencyFingerprintMatched,
                'original_integrity_hash' => $originalHash,
                'original_idempotency_fingerprint' => $original->idempotency_fingerprint,
                'persisted_expected_hash' => $proof->persistedExpectedHash,
                'original_entry_unchanged' => true,
                'base_verifier_status' => 'unverified',
            ],
            metadata: [
                'schema' => 'x-change.account-funding-pay-code-integrity-attestation.v1',
                'domain' => 'account_funding',
                'source' => 'guarded_journal_integrity_attestation',
            ],
        ));
    }

    /**
     * @return array<string, string>
     */
    private function metadata(string $source): array
    {
        return [
            'schema' => 'x-change.account-funding-pay-code-journal.v1',
            'domain' => 'account_funding',
            'source' => $source,
            'accounting_authority' => 'treasury_position_operations',
        ];
    }
}
