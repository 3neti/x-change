<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Models\StandingFundingAddressBindingMigration;
use LBHurtado\XChange\Services\Funding\StandingFundingAddressBindingJournal;
use LBHurtado\XChange\Services\Treasury\TreasuryOperatorAuthority;

final readonly class RequestStandingFundingAddressBindingMigration
{
    public function __construct(
        private InspectStandingFundingAddressBindingMigration $inspect,
        private TreasuryOperatorAuthority $authority,
        private AuditLoggerContract $audit,
        private StandingFundingAddressBindingJournal $journal,
    ) {}

    public function handle(
        StandingFundingAddress $address,
        Model $maker,
        string $idempotencyKey,
        ?CarbonImmutable $effectiveAt = null,
    ): StandingFundingAddressBindingMigration {
        $this->authority->assertAllows($maker, TreasuryOperatorCapability::RequestFundingBindings);
        $idempotencyKey = trim($idempotencyKey);

        if ($idempotencyKey === '' || mb_strlen($idempotencyKey) > 191) {
            throw new \InvalidArgumentException('A stable binding migration idempotency key is required.');
        }

        $idempotencyHash = hash('sha256', $idempotencyKey);
        $existing = StandingFundingAddressBindingMigration::query()
            ->where('idempotency_key_hash', $idempotencyHash)
            ->first();

        if ($existing instanceof StandingFundingAddressBindingMigration) {
            return $this->assertReplay($existing, $address, $maker, $effectiveAt);
        }

        $lock = Cache::lock('x-change:standing-funding-address:'.$address->getKey(), 120);
        $migration = $lock->block(5, function () use (
            $address,
            $maker,
            $idempotencyHash,
            $effectiveAt,
        ): StandingFundingAddressBindingMigration {
            return DB::transaction(function () use (
                $address,
                $maker,
                $idempotencyHash,
                $effectiveAt,
            ): StandingFundingAddressBindingMigration {
                $lockedAddress = StandingFundingAddress::query()
                    ->lockForUpdate()
                    ->findOrFail($address->getKey());
                $replay = StandingFundingAddressBindingMigration::query()
                    ->where('idempotency_key_hash', $idempotencyHash)
                    ->lockForUpdate()
                    ->first();

                if ($replay instanceof StandingFundingAddressBindingMigration) {
                    return $this->assertReplay($replay, $lockedAddress, $maker, $effectiveAt);
                }

                $preview = $this->inspect->handle($lockedAddress, $effectiveAt);

                if ($preview['safe'] !== true) {
                    throw new \DomainException('The Standing Funding Address binding migration is not safe to request.');
                }

                $migration = StandingFundingAddressBindingMigration::query()->create([
                    'standing_funding_address_id' => $lockedAddress->getKey(),
                    'from_account_reference_ciphertext' => $preview['current_account_reference'],
                    'from_account_reference_hash' => hash('sha256', $preview['current_account_reference']),
                    'to_account_reference_ciphertext' => $preview['target_account_reference'],
                    'to_account_reference_hash' => hash('sha256', $preview['target_account_reference']),
                    'proposed_binding_key' => $preview['target_binding_key'],
                    'proposed_destination_snapshot_ciphertext' => $preview['target_destination_snapshot'],
                    'proposed_destination_fingerprint' => $preview['target_destination_fingerprint'],
                    'evidence_snapshot' => $preview['evidence'],
                    'evidence_hash' => $preview['evidence_hash'],
                    'idempotency_key_hash' => $idempotencyHash,
                    'maker_type' => $maker->getMorphClass(),
                    'maker_id' => $maker->getKey(),
                    'requested_at' => now(),
                ]);
                $this->journal->record(
                    $migration,
                    'funding.standing_address.binding_migration.requested',
                    $maker,
                );

                return $migration;
            }, attempts: 5);
        });

        if ($migration->wasRecentlyCreated) {
            $this->audit->log('funding.standing_address.binding_migration_requested', [
                'binding_migration_reference' => $migration->reference,
                'standing_funding_address_reference' => $address->reference,
                'evidence_hash' => $migration->evidence_hash,
                'actor_type' => $maker->getMorphClass(),
                'actor_id' => (string) $maker->getKey(),
            ]);
        }

        return $migration;
    }

    private function assertReplay(
        StandingFundingAddressBindingMigration $existing,
        StandingFundingAddress $address,
        Model $maker,
        ?CarbonImmutable $effectiveAt,
    ): StandingFundingAddressBindingMigration {
        $sameMaker = $existing->maker_type === $maker->getMorphClass()
            && (string) $existing->maker_id === (string) $maker->getKey();
        $sameEffectiveAt = $effectiveAt === null || CarbonImmutable::parse(
            data_get($existing->evidence_snapshot, 'proposed_effective_at'),
        )->equalTo($effectiveAt);

        if ($existing->standing_funding_address_id !== $address->getKey()
            || ! $sameMaker
            || ! $sameEffectiveAt) {
            throw new \DomainException('The binding migration idempotency key conflicts with another request.');
        }

        return $existing;
    }
}
