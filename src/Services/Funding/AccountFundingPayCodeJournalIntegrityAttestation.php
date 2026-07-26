<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Models\VoucherClaimOutcomeSelection;
use LBHurtado\XJournal\Data\JournalTimestampPrecisionProofData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\JournalIntegrityVerifier;
use LBHurtado\XJournal\Services\JournalTimestampPrecisionLossDetector;
use RuntimeException;

final readonly class AccountFundingPayCodeJournalIntegrityAttestation
{
    public function __construct(
        private AccountFundingPayCodeJournal $journal,
        private JournalIntegrityVerifier $verifier,
        private JournalTimestampPrecisionLossDetector $detector,
    ) {}

    /**
     * @param  list<int>  $voucherIds
     * @return array<string, mixed>
     */
    public function inspect(array $voucherIds): array
    {
        $contexts = array_map(
            fn (int $voucherId): array => $this->validatedContext($voucherId),
            $this->uniqueVoucherIds($voucherIds),
        );

        return $this->result($contexts, committed: false);
    }

    /**
     * @param  list<int>  $voucherIds
     * @return array<string, mixed>
     */
    public function attest(
        array $voucherIds,
        string $authorizationReference,
    ): array {
        $authorizationReference = trim($authorizationReference);

        if (
            $authorizationReference === ''
            || mb_strlen($authorizationReference) > 191
        ) {
            throw new RuntimeException(
                'A stable authorization reference of at most 191 characters is required.',
            );
        }

        $contexts = array_map(
            fn (int $voucherId): array => $this->validatedContext($voucherId),
            $this->uniqueVoucherIds($voucherIds),
        );

        DB::transaction(function () use (
            &$contexts,
            $authorizationReference,
        ): void {
            foreach ($contexts as &$context) {
                if ($context['already_attested']) {
                    continue;
                }

                Voucher::query()
                    ->whereKey($context['voucher']->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                $locked = ExecutionJournalEntry::query()
                    ->whereKey($context['inspection']->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertEntryUnchanged(
                    $context['inspection'],
                    $locked,
                );
                $context['attestation'] = $this->journal
                    ->recordTimestampPrecisionAttestation(
                        $locked,
                        $context['proof'],
                        $authorizationReference,
                    );
            }
        }, attempts: 5);

        return $this->result($contexts, committed: true);
    }

    /**
     * @return array{
     *     voucher: Voucher,
     *     inspection: ExecutionJournalEntry,
     *     proof: JournalTimestampPrecisionProofData,
     *     attestation: ?ExecutionJournalEntry,
     *     already_attested: bool
     * }
     */
    private function validatedContext(int $voucherId): array
    {
        if ($voucherId <= 0) {
            throw new RuntimeException(
                'Every Voucher ID must be a positive integer; never provide a raw Pay Code.',
            );
        }

        $voucher = Voucher::query()->findOrFail($voucherId);
        $claims = VoucherClaim::query()
            ->where('voucher_id', $voucherId)
            ->where('settlement_mode', 'account_funding')
            ->get();
        $selections = VoucherClaimOutcomeSelection::query()
            ->where('voucher_id', $voucherId)
            ->where('outcome_key', 'account_funding')
            ->get();

        if (
            $claims->count() !== 1
            || $selections->count() !== 1
        ) {
            throw new RuntimeException(
                'The Voucher must have exactly one Account Funding claim and outcome selection.',
            );
        }

        $claim = $claims->sole();
        $selection = $selections->sole();

        if (
            $claim->status !== 'succeeded'
            || $claim->completed_at === null
            || $selection->claimant_type !== $claim->claimant_type
            || (string) $selection->claimant_id
                !== (string) $claim->claimant_id
        ) {
            throw new RuntimeException(
                'The Account Funding claim is not canonically complete.',
            );
        }

        $inspections = ExecutionJournalEntry::query()
            ->where('event_type', 'account_funding.pay_code.inspected')
            ->where('subject_type', 'voucher')
            ->where('subject_id', (string) $voucherId)
            ->get();

        if ($inspections->count() !== 1) {
            throw new RuntimeException(
                'The Voucher must have exactly one Account Funding inspection journal entry.',
            );
        }

        $inspection = $inspections->sole();
        $this->assertInspectionShape(
            $inspection,
            $voucherId,
            (string) $claim->claimant_type,
            (string) $claim->claimant_id,
        );
        $this->assertChainContinuity($inspection);
        $this->assertOnlyHashMismatch($inspection);
        $proof = $this->detector->prove($inspection);

        if (
            ! $proof->proved
            || $proof->candidateCount !== 1
            || ! $proof->idempotencyFingerprintMatched
        ) {
            throw new RuntimeException(
                'Timestamp precision loss could not be uniquely proved by both journal hashes.',
            );
        }

        $attestation = ExecutionJournalEntry::query()
            ->where(
                'idempotency_key',
                $this->attestationIdempotencyKey($inspection),
            )
            ->first();

        if (
            $attestation instanceof ExecutionJournalEntry
            && (
                $attestation->event_type
                    !== 'account_funding.pay_code.integrity_exception_attested'
                || data_get($attestation->payload, 'classification')
                    !== 'timestamp_precision_loss'
            )
        ) {
            throw new RuntimeException(
                'The attestation idempotency key belongs to an unexpected journal event.',
            );
        }

        return [
            'voucher' => $voucher,
            'inspection' => $inspection,
            'proof' => $proof,
            'attestation' => $attestation,
            'already_attested' => $attestation instanceof ExecutionJournalEntry,
        ];
    }

    private function assertInspectionShape(
        ExecutionJournalEntry $inspection,
        int $voucherId,
        string $claimantType,
        string $claimantId,
    ): void {
        $fingerprint = str_replace(
            'x-change:account-funding-pay-code:inspected:',
            '',
            (string) $inspection->idempotency_key,
        );

        if (
            data_get($inspection->subject, 'type') !== 'voucher'
            || (string) data_get($inspection->subject, 'id')
                !== (string) $voucherId
            || (string) data_get($inspection->references, 'execution_id')
                !== (string) $voucherId
            || data_get($inspection->actor, 'type') !== $claimantType
            || (string) data_get($inspection->actor, 'id') !== $claimantId
            || data_get($inspection->payload, 'status') !== 'eligible'
            || data_get(
                $inspection->payload,
                'inspection_token_persisted',
            ) !== false
            || data_get(
                $inspection->payload,
                'raw_pay_code_persisted',
            ) !== false
            || data_get($inspection->metadata, 'schema')
                !== 'x-change.account-funding-pay-code-journal.v1'
            || data_get($inspection->metadata, 'domain')
                !== 'account_funding'
            || data_get($inspection->metadata, 'source')
                !== 'cockpit_account_funding_pay_code_inspection'
            || data_get($inspection->metadata, 'accounting_authority')
                !== 'treasury_position_operations'
            || $fingerprint === (string) $inspection->idempotency_key
            || data_get($inspection->references, 'correlation_id')
                !== 'account-funding-inspection:'.$fingerprint
        ) {
            throw new RuntimeException(
                'The journal entry is not an eligible Account Funding Pay Code inspection.',
            );
        }
    }

    private function assertChainContinuity(
        ExecutionJournalEntry $inspection,
    ): void {
        $previous = ExecutionJournalEntry::query()
            ->where(
                $inspection->getQualifiedKeyName(),
                '<',
                $inspection->getKey(),
            )
            ->orderByDesc('id')
            ->first();
        $next = ExecutionJournalEntry::query()
            ->where(
                $inspection->getQualifiedKeyName(),
                '>',
                $inspection->getKey(),
            )
            ->orderBy('id')
            ->first();
        $actualPreviousHash = data_get(
            $inspection->integrity,
            'previous_hash',
        );
        $expectedPreviousHash = $previous === null
            ? null
            : data_get($previous->integrity, 'hash');
        $actualHash = data_get($inspection->integrity, 'hash');

        if (
            $actualPreviousHash !== $expectedPreviousHash
            || (
                $next instanceof ExecutionJournalEntry
                && data_get($next->integrity, 'previous_hash')
                    !== $actualHash
            )
        ) {
            throw new RuntimeException(
                'The inspection entry is not continuous with its journal neighbors.',
            );
        }
    }

    private function assertOnlyHashMismatch(
        ExecutionJournalEntry $inspection,
    ): void {
        $prefix = ExecutionJournalEntry::query()
            ->where(
                $inspection->getQualifiedKeyName(),
                '<=',
                $inspection->getKey(),
            )
            ->orderBy('id')
            ->get();
        $issueCodes = collect($this->verifier->verify($prefix)->issues)
            ->filter(
                fn (object $issue): bool => $issue->referenceNumber
                    === $inspection->reference_number,
            )
            ->map(fn (object $issue): string => $issue->code)
            ->values()
            ->all();

        if ($issueCodes !== ['hash_mismatch']) {
            throw new RuntimeException(
                'The inspection entry must have exactly one hash mismatch and no other integrity issue.',
            );
        }
    }

    private function assertEntryUnchanged(
        ExecutionJournalEntry $before,
        ExecutionJournalEntry $locked,
    ): void {
        if (
            $before->getRawOriginal('occurred_at')
                !== $locked->getRawOriginal('occurred_at')
            || $before->getRawOriginal('integrity')
                !== $locked->getRawOriginal('integrity')
            || $before->idempotency_fingerprint
                !== $locked->idempotency_fingerprint
        ) {
            throw new RuntimeException(
                'The inspection entry changed after forensic validation.',
            );
        }
    }

    /**
     * @param  list<int>  $voucherIds
     * @return list<int>
     */
    private function uniqueVoucherIds(array $voucherIds): array
    {
        $voucherIds = array_values(array_unique($voucherIds));

        if ($voucherIds === []) {
            throw new RuntimeException(
                'At least one numeric Voucher ID is required; never provide a raw Pay Code.',
            );
        }

        return $voucherIds;
    }

    private function attestationIdempotencyKey(
        ExecutionJournalEntry $inspection,
    ): string {
        return 'x-change:account-funding-pay-code:integrity-exception:'
            .hash(
                'sha256',
                $inspection->reference_number.':'.data_get(
                    $inspection->integrity,
                    'hash',
                ),
            );
    }

    /**
     * @param  list<array{
     *     voucher: Voucher,
     *     inspection: ExecutionJournalEntry,
     *     proof: JournalTimestampPrecisionProofData,
     *     attestation: ?ExecutionJournalEntry,
     *     already_attested: bool
     * }>  $contexts
     * @return array<string, mixed>
     */
    private function result(array $contexts, bool $committed): array
    {
        $alreadyAttested = collect($contexts)->every(
            fn (array $context): bool => $context['already_attested'],
        );

        return [
            'schema' => 'x-change.account-funding-pay-code-integrity-attestation.v1',
            'success' => true,
            'status' => match (true) {
                $alreadyAttested => 'already_attested',
                $committed => 'attested',
                default => 'ready',
            },
            'classification' => 'timestamp_precision_loss',
            'entries' => array_map(
                static fn (array $context): array => [
                    'voucher_id' => (int) $context['voucher']->getKey(),
                    'original_reference_number' => (string) $context['inspection']->reference_number,
                    'persisted_occurred_at' => $context['inspection']->occurred_at->toJSON(),
                    'recovered_hash_basis_occurred_at' => $context['proof']->recoveredOccurredAt,
                    'recovered_microseconds' => $context['proof']->recoveredMicroseconds,
                    'candidate_count' => $context['proof']->candidateCount,
                    'idempotency_fingerprint_reproduced' => $context['proof']->idempotencyFingerprintMatched,
                    'attestation_reference_number' => $context['attestation']?->reference_number,
                ],
                $contexts,
            ),
            'committed' => $committed,
            'original_entries_unchanged' => true,
            'base_verifier_remains_unverified' => true,
            'provider_calls' => false,
            'treasury_changed' => false,
        ];
    }
}
