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
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Models\StandingFundingAddressBindingHead;
use LBHurtado\XChange\Models\StandingFundingAddressBindingMigration;
use LBHurtado\XChange\Models\StandingFundingAddressBindingRevision;
use LBHurtado\XChange\Services\Funding\StandingFundingAddressBindingJournal;
use LBHurtado\XChange\Services\Treasury\TreasuryOperatorAuthority;

final readonly class ActivateStandingFundingAddressBindingMigration
{
    public function __construct(
        private InspectStandingFundingAddressBindingMigration $inspect,
        private TreasuryOperatorAuthority $authority,
        private AuditLoggerContract $audit,
        private StandingFundingAddressBindingJournal $journal,
    ) {}

    public function handle(
        StandingFundingAddressBindingMigration $migration,
        Model $executor,
    ): StandingFundingAddressBindingMigration {
        $this->authority->assertAllows($executor, TreasuryOperatorCapability::ExecuteFundingBindings);
        $lock = Cache::lock(
            'x-change:standing-funding-address:'.$migration->standing_funding_address_id,
            120,
        );
        $transitioned = false;
        $activated = $lock->block(5, function () use ($migration, $executor, &$transitioned): StandingFundingAddressBindingMigration {
            return DB::transaction(function () use ($migration, $executor, &$transitioned): StandingFundingAddressBindingMigration {
                $locked = StandingFundingAddressBindingMigration::query()
                    ->lockForUpdate()
                    ->findOrFail($migration->getKey());

                if ($locked->status === StandingFundingAddressBindingMigrationStatus::Activated) {
                    if ($locked->activated_by_type !== $executor->getMorphClass()
                        || (string) $locked->activated_by_id !== (string) $executor->getKey()) {
                        throw new \DomainException('The binding migration activation replay conflicts with its recorded executor.');
                    }

                    return $locked;
                }

                if ($locked->status !== StandingFundingAddressBindingMigrationStatus::Approved) {
                    throw new \DomainException('The binding migration is not approved for activation.');
                }

                if ($locked->maker_type === $executor->getMorphClass()
                    && (string) $locked->maker_id === (string) $executor->getKey()) {
                    throw new \DomainException('The binding migration executor must be independent from its maker.');
                }

                $address = StandingFundingAddress::query()
                    ->lockForUpdate()
                    ->findOrFail($locked->standing_funding_address_id);
                $effectiveAt = CarbonImmutable::parse(
                    data_get($locked->evidence_snapshot, 'proposed_effective_at'),
                );

                if (! $effectiveAt->isFuture()) {
                    $locked->status = StandingFundingAddressBindingMigrationStatus::ReviewRequired;
                    $locked->save();
                    $this->journal->record(
                        $locked,
                        'funding.standing_address.binding_migration.review_required',
                        $executor,
                    );
                    $transitioned = true;

                    return $locked->refresh();
                }

                $preview = $this->inspect->handle($address, $effectiveAt, $locked->getKey());

                if (! hash_equals($locked->evidence_hash, $preview['evidence_hash'])) {
                    $locked->status = StandingFundingAddressBindingMigrationStatus::ReviewRequired;
                    $locked->save();
                    $this->journal->record(
                        $locked,
                        'funding.standing_address.binding_migration.review_required',
                        $executor,
                    );
                    $transitioned = true;

                    return $locked->refresh();
                }

                $head = StandingFundingAddressBindingHead::query()
                    ->whereKey($address->getKey())
                    ->lockForUpdate()
                    ->first();
                $previous = $head?->currentBindingRevision()->first();

                if (! $previous instanceof StandingFundingAddressBindingRevision) {
                    $previous = StandingFundingAddressBindingRevision::query()
                        ->whereBelongsTo($address)
                        ->orderByDesc('binding_version')
                        ->lockForUpdate()
                        ->first();
                }

                if (! $previous instanceof StandingFundingAddressBindingRevision) {
                    $previous = StandingFundingAddressBindingRevision::query()->create([
                        'standing_funding_address_id' => $address->getKey(),
                        'binding_version' => 1,
                        'account_reference_ciphertext' => $locked->from_account_reference_ciphertext,
                        'account_reference_hash' => $locked->from_account_reference_hash,
                        'binding_key' => $address->binding_key,
                        'destination_snapshot_ciphertext' => $address->destination_snapshot_ciphertext,
                        'destination_fingerprint' => $address->destination_fingerprint,
                        'reason' => 'legacy_binding_baseline',
                        'evidence_snapshot' => [
                            'schema' => 'x-change.funding-standing-address-binding-revision-evidence.v1',
                            'migration_reference' => $locked->reference,
                            'standing_funding_address_reference' => $address->reference,
                            'role' => 'legacy_baseline',
                            'account_reference_hash' => $locked->from_account_reference_hash,
                        ],
                        'evidence_hash' => hash('sha256', 'legacy:'.$locked->evidence_hash),
                        'approval_reference' => $locked->approval_reference,
                        'activated_by_type' => $executor->getMorphClass(),
                        'activated_by_id' => $executor->getKey(),
                        'effective_at' => $address->activated_at ?? $address->created_at,
                    ]);
                }

                $target = StandingFundingAddressBindingRevision::query()->create([
                    'standing_funding_address_id' => $address->getKey(),
                    'binding_version' => $previous->binding_version + 1,
                    'previous_binding_revision_id' => $previous->getKey(),
                    'account_reference_ciphertext' => $locked->to_account_reference_ciphertext,
                    'account_reference_hash' => $locked->to_account_reference_hash,
                    'binding_key' => $locked->proposed_binding_key,
                    'destination_snapshot_ciphertext' => $locked->proposed_destination_snapshot_ciphertext,
                    'destination_fingerprint' => $locked->proposed_destination_fingerprint,
                    'reason' => 'treasury_client_funds_cutover',
                    'evidence_snapshot' => [
                        'schema' => 'x-change.funding-standing-address-binding-revision-evidence.v1',
                        'migration_reference' => $locked->reference,
                        'standing_funding_address_reference' => $address->reference,
                        'role' => 'treasury_client_funds',
                        'account_reference_hash' => $locked->to_account_reference_hash,
                        'migration_evidence_hash' => $locked->evidence_hash,
                    ],
                    'evidence_hash' => hash('sha256', 'target:'.$locked->evidence_hash),
                    'approval_reference' => $locked->approval_reference,
                    'activated_by_type' => $executor->getMorphClass(),
                    'activated_by_id' => $executor->getKey(),
                    'effective_at' => $effectiveAt,
                ]);

                $head ??= new StandingFundingAddressBindingHead([
                    'standing_funding_address_id' => $address->getKey(),
                ]);
                $head->current_binding_revision_id = $target->getKey();
                $head->saveQuietly();

                $locked->status = StandingFundingAddressBindingMigrationStatus::Activated;
                $locked->activated_by_type = $executor->getMorphClass();
                $locked->activated_by_id = $executor->getKey();
                $locked->activated_binding_revision_id = $target->getKey();
                $locked->activated_at = now();
                $locked->save();
                $this->journal->record(
                    $locked,
                    'funding.standing_address.binding_revision.activated',
                    $executor,
                );
                $transitioned = true;

                return $locked->refresh();
            }, attempts: 5);
        });

        if ($transitioned) {
            $event = $activated->status === StandingFundingAddressBindingMigrationStatus::Activated
                ? 'funding.standing_address.binding_revision_activated'
                : 'funding.standing_address.binding_migration_review_required';
            $this->audit->log($event, [
                'binding_migration_reference' => $activated->reference,
                'standing_funding_address_id' => $activated->standing_funding_address_id,
                'binding_revision_id' => $activated->activated_binding_revision_id,
                'evidence_hash' => $activated->evidence_hash,
                'actor_type' => $executor->getMorphClass(),
                'actor_id' => (string) $executor->getKey(),
            ]);
        }

        return $activated;
    }
}
