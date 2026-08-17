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
use LBHurtado\XChange\Models\StandingFundingAddressBindingMigration;
use LBHurtado\XChange\Services\Funding\StandingFundingAddressBindingJournal;
use LBHurtado\XChange\Services\Treasury\TreasuryOperatorAuthority;

final readonly class ApproveStandingFundingAddressBindingMigration
{
    public function __construct(
        private InspectStandingFundingAddressBindingMigration $inspect,
        private TreasuryOperatorAuthority $authority,
        private AuditLoggerContract $audit,
        private StandingFundingAddressBindingJournal $journal,
    ) {}

    public function handle(
        StandingFundingAddressBindingMigration $migration,
        Model $checker,
        string $approvalReference,
    ): StandingFundingAddressBindingMigration {
        $this->authority->assertAllows($checker, TreasuryOperatorCapability::ApproveFundingBindings);
        $approvalReference = trim($approvalReference);

        if ($approvalReference === '') {
            throw new \InvalidArgumentException('A binding migration approval reference is required.');
        }

        $lock = Cache::lock(
            'x-change:standing-funding-address:'.$migration->standing_funding_address_id,
            120,
        );
        $transitioned = false;
        $approved = $lock->block(5, function () use (
            $migration,
            $checker,
            $approvalReference,
            &$transitioned,
        ): StandingFundingAddressBindingMigration {
            return DB::transaction(function () use (
                $migration,
                $checker,
                $approvalReference,
                &$transitioned,
            ): StandingFundingAddressBindingMigration {
                $locked = StandingFundingAddressBindingMigration::query()
                    ->lockForUpdate()
                    ->findOrFail($migration->getKey());

                if ($locked->status === StandingFundingAddressBindingMigrationStatus::Approved
                    || $locked->status === StandingFundingAddressBindingMigrationStatus::Activated) {
                    if ($locked->checker_type !== $checker->getMorphClass()
                        || (string) $locked->checker_id !== (string) $checker->getKey()
                        || $locked->approval_reference !== $approvalReference) {
                        throw new \DomainException('The binding migration approval replay conflicts with its recorded evidence.');
                    }

                    return $locked;
                }

                if ($locked->status !== StandingFundingAddressBindingMigrationStatus::AwaitingApproval) {
                    throw new \DomainException('The binding migration is not awaiting approval.');
                }

                if ($locked->maker_type === $checker->getMorphClass()
                    && (string) $locked->maker_id === (string) $checker->getKey()) {
                    throw new \DomainException('The binding migration checker must be independent from its maker.');
                }

                $address = StandingFundingAddress::query()
                    ->lockForUpdate()
                    ->findOrFail($locked->standing_funding_address_id);
                $effectiveAt = CarbonImmutable::parse(
                    data_get($locked->evidence_snapshot, 'proposed_effective_at'),
                );
                $preview = $this->inspect->handle($address, $effectiveAt, $locked->getKey());

                if (! hash_equals($locked->evidence_hash, $preview['evidence_hash'])) {
                    $locked->status = StandingFundingAddressBindingMigrationStatus::ReviewRequired;
                    $locked->save();
                    $this->journal->record(
                        $locked,
                        'funding.standing_address.binding_migration.review_required',
                        $checker,
                    );
                    $transitioned = true;

                    return $locked->refresh();
                }

                $locked->status = StandingFundingAddressBindingMigrationStatus::Approved;
                $locked->checker_type = $checker->getMorphClass();
                $locked->checker_id = $checker->getKey();
                $locked->approval_reference = $approvalReference;
                $locked->approved_at = now();
                $locked->save();
                $this->journal->record(
                    $locked,
                    'funding.standing_address.binding_migration.approved',
                    $checker,
                );
                $transitioned = true;

                return $locked->refresh();
            }, attempts: 5);
        });

        if ($transitioned) {
            $event = $approved->status === StandingFundingAddressBindingMigrationStatus::Approved
                ? 'funding.standing_address.binding_migration_approved'
                : 'funding.standing_address.binding_migration_review_required';
            $this->audit->log($event, [
                'binding_migration_reference' => $approved->reference,
                'evidence_hash' => $approved->evidence_hash,
                'actor_type' => $checker->getMorphClass(),
                'actor_id' => (string) $checker->getKey(),
            ]);
        }

        return $approved;
    }
}
