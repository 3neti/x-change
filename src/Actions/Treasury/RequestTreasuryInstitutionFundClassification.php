<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Treasury;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LBHurtado\Wallet\Contracts\SystemUserResolverContract;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionOperationType;
use LBHurtado\Wallet\Treasury\Enums\TreasuryPositionPurpose;
use LBHurtado\Wallet\Treasury\Models\TreasuryPosition;
use LBHurtado\Wallet\Treasury\Models\TreasuryPositionOperation;
use LBHurtado\XChange\Enums\TreasuryInstitutionFundClassificationStatus;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Models\TreasuryInstitutionFundClassification;
use LBHurtado\XChange\Services\Treasury\TreasuryInstitutionFundClassificationJournal;
use LBHurtado\XChange\Services\Treasury\TreasuryOperatorAuthority;

final readonly class RequestTreasuryInstitutionFundClassification
{
    public function __construct(
        private TreasuryOperatorAuthority $authority,
        private SystemUserResolverContract $systemUsers,
        private TreasuryInstitutionFundClassificationJournal $journal,
    ) {}

    public function handle(
        string $evidenceOperationReference,
        string $ownershipBasis,
        string $idempotencyReference,
        Model $maker,
    ): TreasuryInstitutionFundClassification {
        $this->authority->assertAllows($maker, TreasuryOperatorCapability::RequestInstitutionFunds);
        $evidenceOperationReference = trim($evidenceOperationReference);
        $ownershipBasis = trim($ownershipBasis);
        $idempotencyReference = trim($idempotencyReference);

        if ($evidenceOperationReference === '' || $ownershipBasis === '' || $idempotencyReference === '') {
            throw new DomainException('The Institution-Owned Funds classification request is incomplete.');
        }

        return DB::transaction(function () use (
            $evidenceOperationReference,
            $ownershipBasis,
            $idempotencyReference,
            $maker,
        ): TreasuryInstitutionFundClassification {
            $evidence = TreasuryPositionOperation::query()
                ->with('destinationPosition')
                ->where('operation_reference', $evidenceOperationReference)
                ->lockForUpdate()
                ->firstOrFail();
            $source = $this->assertAuthoritativeEvidence($evidence);
            $facts = [
                'evidence_operation_reference' => $evidence->operation_reference,
                'evidence_reference' => (string) $evidence->external_reference,
                'amount_minor' => $evidence->amount_minor,
                'currency' => $evidence->currency,
                'connection_reference' => $source->connection_reference,
                'ownership_basis' => $ownershipBasis,
            ];
            $requestHash = hash('sha256', json_encode($facts, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $idempotencyHash = hash('sha256', $idempotencyReference);
            $existing = TreasuryInstitutionFundClassification::query()
                ->where('idempotency_reference_hash', $idempotencyHash)
                ->orWhere('evidence_operation_reference', $evidence->operation_reference)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof TreasuryInstitutionFundClassification) {
                if (! hash_equals($existing->request_hash, $requestHash)) {
                    throw new DomainException('The classification evidence or idempotency reference is already governed by different input.');
                }

                return $existing;
            }

            $classification = TreasuryInstitutionFundClassification::query()->create([
                'reference' => (string) Str::ulid(),
                'status' => TreasuryInstitutionFundClassificationStatus::AwaitingApproval,
                ...$facts,
                'request_hash' => $requestHash,
                'idempotency_reference_hash' => $idempotencyHash,
                'maker_type' => $maker->getMorphClass(),
                'maker_id' => (string) $maker->getKey(),
                'submitted_at' => now(),
            ]);
            $this->journal->record($classification, 'treasury.institution_funds.requested', $maker);

            return $classification;
        }, attempts: 3);
    }

    private function assertAuthoritativeEvidence(TreasuryPositionOperation $evidence): TreasuryPosition
    {
        $source = $evidence->destinationPosition;
        $system = $this->systemUsers->resolve();

        if (! $source instanceof TreasuryPosition
            || ! $system instanceof Model
            || $evidence->operation_type !== TreasuryPositionOperationType::Recognition
            || $evidence->status !== 'committed'
            || $evidence->amount_minor <= 0
            || trim((string) $evidence->external_reference) === ''
            || data_get($evidence->metadata, 'source') !== 'provider_balance_reconciliation'
            || $source->purpose !== TreasuryPositionPurpose::LegacyUnattributed
            || $source->principal_type !== $system->getMorphClass()
            || (string) $source->principal_id !== (string) $system->getKey()
            || $source->currency !== $evidence->currency) {
            throw new DomainException('Only authoritative provider-balance evidence in Legacy Unattributed may be classified.');
        }

        return $source;
    }
}
