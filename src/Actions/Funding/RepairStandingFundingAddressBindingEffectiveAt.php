<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Enums\StandingFundingAddressBindingMigrationStatus;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Models\AccountFundingReceipt;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Models\StandingFundingAddressBindingEffectiveTimeCorrection;
use LBHurtado\XChange\Models\StandingFundingAddressBindingHead;
use LBHurtado\XChange\Models\StandingFundingAddressBindingMigration;
use LBHurtado\XChange\Models\StandingFundingAddressBindingRevision;
use LBHurtado\XChange\Services\Funding\StandingFundingAddressBindingJournal;
use LBHurtado\XChange\Services\Treasury\TreasuryOperatorAuthority;

final readonly class RepairStandingFundingAddressBindingEffectiveAt
{
    public function __construct(
        private TreasuryOperatorAuthority $authority,
        private AuditLoggerContract $audit,
        private StandingFundingAddressBindingJournal $journal,
    ) {}

    public function handle(
        StandingFundingAddressBindingMigration $migration,
        Model $executor,
        string $idempotencyKey,
        string $authorizationReference,
    ): StandingFundingAddressBindingEffectiveTimeCorrection {
        $this->authority->assertAllows($executor, TreasuryOperatorCapability::ExecuteFundingBindings);
        $idempotencyHash = hash('sha256', $this->required($idempotencyKey, 'idempotency key'));
        $authorizationReference = $this->required($authorizationReference, 'authorization reference');
        $lock = Cache::lock(
            'x-change:standing-funding-address:'.$migration->standing_funding_address_id,
            120,
        );
        $created = false;

        $correction = $lock->block(5, function () use (
            $migration,
            $executor,
            $idempotencyHash,
            $authorizationReference,
            &$created,
        ): StandingFundingAddressBindingEffectiveTimeCorrection {
            return DB::transaction(function () use (
                $migration,
                $executor,
                $idempotencyHash,
                $authorizationReference,
                &$created,
            ): StandingFundingAddressBindingEffectiveTimeCorrection {
                $locked = StandingFundingAddressBindingMigration::query()
                    ->lockForUpdate()
                    ->findOrFail($migration->getKey());
                $existing = StandingFundingAddressBindingEffectiveTimeCorrection::query()
                    ->where('idempotency_key_hash', $idempotencyHash)
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof StandingFundingAddressBindingEffectiveTimeCorrection) {
                    return $this->assertReplay(
                        $existing,
                        $locked,
                        $executor,
                        $authorizationReference,
                        $idempotencyHash,
                    );
                }

                $existingForMigration = StandingFundingAddressBindingEffectiveTimeCorrection::query()
                    ->where('standing_funding_address_binding_migration_id', $locked->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($existingForMigration instanceof StandingFundingAddressBindingEffectiveTimeCorrection) {
                    return $this->assertReplay(
                        $existingForMigration,
                        $locked,
                        $executor,
                        $authorizationReference,
                        $idempotencyHash,
                    );
                }

                if ($locked->status !== StandingFundingAddressBindingMigrationStatus::Activated
                    || $locked->activated_binding_revision_id === null) {
                    throw new \DomainException('Only an activated binding migration can receive an effective-time correction.');
                }

                $computedEvidenceHash = hash(
                    'sha256',
                    json_encode($locked->evidence_snapshot, JSON_THROW_ON_ERROR),
                );

                if (! hash_equals($locked->evidence_hash, $computedEvidenceHash)) {
                    throw new \DomainException('The approved binding migration evidence failed integrity verification.');
                }

                if ($locked->maker_type === $executor->getMorphClass()
                    && (string) $locked->maker_id === (string) $executor->getKey()) {
                    throw new \DomainException('The effective-time correction executor must be independent from its maker.');
                }

                $approvedValue = trim((string) data_get($locked->evidence_snapshot, 'proposed_effective_at'));

                if (! preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', $approvedValue)) {
                    throw new \DomainException('The approved cutover evidence has no explicit timezone offset.');
                }

                $approvedEffectiveAt = CarbonImmutable::parse($approvedValue);
                $correctedEffectiveAt = $approvedEffectiveAt->utc();
                $buggyEffectiveAt = CarbonImmutable::createFromFormat(
                    '!Y-m-d H:i:s.u',
                    $approvedEffectiveAt->format('Y-m-d H:i:s.u'),
                    'UTC',
                );

                if (! $buggyEffectiveAt instanceof CarbonImmutable) {
                    throw new \DomainException('The approved cutover evidence cannot be normalized.');
                }

                $revision = StandingFundingAddressBindingRevision::query()
                    ->lockForUpdate()
                    ->findOrFail($locked->activated_binding_revision_id);
                $head = StandingFundingAddressBindingHead::query()
                    ->whereKey($locked->standing_funding_address_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $address = StandingFundingAddress::query()
                    ->lockForUpdate()
                    ->findOrFail($locked->standing_funding_address_id);

                if ($revision->standing_funding_address_id !== $locked->standing_funding_address_id
                    || $head->current_binding_revision_id !== $revision->getKey()
                    || data_get($revision->evidence_snapshot, 'migration_reference') !== $locked->reference
                    || data_get($revision->evidence_snapshot, 'migration_evidence_hash') !== $locked->evidence_hash) {
                    throw new \DomainException('The active binding revision does not match its approved migration evidence.');
                }

                if (! $revision->effective_at->equalTo($buggyEffectiveAt)
                    || $correctedEffectiveAt->isFuture()
                    || ! $buggyEffectiveAt->isFuture()) {
                    throw new \DomainException('The binding revision is not in the narrowly correctable timezone state.');
                }

                $competingRevisionExists = StandingFundingAddressBindingRevision::query()
                    ->whereBelongsTo($address)
                    ->where('binding_version', '>', $revision->binding_version)
                    ->exists();
                $affectedReceiptExists = AccountFundingReceipt::query()
                    ->where('standing_funding_address_id', $address->getKey())
                    ->where('observed_at', '>=', $correctedEffectiveAt)
                    ->where('observed_at', '<', $buggyEffectiveAt)
                    ->exists();
                $affectedObservationExists = DB::table('provider_funding_observations')
                    ->where('funding_address', 'sha256:'.$address->funding_address_hash)
                    ->where('occurred_at', '>=', $correctedEffectiveAt->format('Y-m-d H:i:s.u'))
                    ->where('occurred_at', '<', $buggyEffectiveAt->format('Y-m-d H:i:s.u'))
                    ->exists();

                if ($competingRevisionExists || $affectedReceiptExists || $affectedObservationExists) {
                    throw new \DomainException('Activity exists in the affected cutover interval; manual review is required.');
                }

                $correctionHash = hash('sha256', implode("\0", [
                    'x-change.funding-standing-address-binding-effective-time-correction.v1',
                    $locked->reference,
                    $revision->reference,
                    $revision->effective_at->toRfc3339String(),
                    $correctedEffectiveAt->toRfc3339String(),
                    $locked->evidence_hash,
                    $authorizationReference,
                ]));
                $correction = StandingFundingAddressBindingEffectiveTimeCorrection::query()->create([
                    'standing_funding_address_binding_revision_id' => $revision->getKey(),
                    'standing_funding_address_binding_migration_id' => $locked->getKey(),
                    'original_effective_at' => $revision->effective_at,
                    'corrected_effective_at' => $correctedEffectiveAt,
                    'approved_evidence_hash' => $locked->evidence_hash,
                    'correction_hash' => $correctionHash,
                    'idempotency_key_hash' => $idempotencyHash,
                    'authorization_reference' => $authorizationReference,
                    'corrected_by_type' => $executor->getMorphClass(),
                    'corrected_by_id' => $executor->getKey(),
                    'reason' => 'timezone_serialization_normalization',
                ]);
                $this->journal->record(
                    $locked,
                    'funding.standing_address.binding_revision.effective_at_corrected',
                    $executor,
                );
                $created = true;

                return $correction;
            }, attempts: 5);
        });

        if ($created) {
            $this->audit->log('funding.standing_address.binding_revision_effective_at_corrected', [
                'binding_migration_reference' => $migration->reference,
                'binding_revision_reference' => $correction->bindingRevision()->value('reference'),
                'approved_evidence_hash' => $correction->approved_evidence_hash,
                'correction_hash' => $correction->correction_hash,
                'actor_type' => $executor->getMorphClass(),
                'actor_id' => (string) $executor->getKey(),
                'provider_calls' => false,
                'qr_regenerated' => false,
                'provider_inventory_changed' => false,
            ]);
        }

        return $correction;
    }

    private function assertReplay(
        StandingFundingAddressBindingEffectiveTimeCorrection $existing,
        StandingFundingAddressBindingMigration $migration,
        Model $executor,
        string $authorizationReference,
        string $idempotencyHash,
    ): StandingFundingAddressBindingEffectiveTimeCorrection {
        if ($existing->standing_funding_address_binding_migration_id !== $migration->getKey()
            || $existing->corrected_by_type !== $executor->getMorphClass()
            || (string) $existing->corrected_by_id !== (string) $executor->getKey()
            || $existing->authorization_reference !== $authorizationReference
            || $existing->approved_evidence_hash !== $migration->evidence_hash
            || $existing->idempotency_key_hash !== $idempotencyHash) {
            throw new \DomainException('The effective-time correction idempotency key conflicts with another request.');
        }

        return $existing;
    }

    private function required(string $value, string $label): string
    {
        $value = trim($value);

        if ($value === '' || mb_strlen($value) > 191) {
            throw new \InvalidArgumentException("A valid {$label} is required.");
        }

        return $value;
    }
}
