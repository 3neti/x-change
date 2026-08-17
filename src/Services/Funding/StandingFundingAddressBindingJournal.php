<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Models\StandingFundingAddressBindingEffectiveTimeCorrection;
use LBHurtado\XChange\Models\StandingFundingAddressBindingMigration;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

final readonly class StandingFundingAddressBindingJournal
{
    public function __construct(private ExecutionJournalRecorder $recorder) {}

    public function record(
        StandingFundingAddressBindingMigration $migration,
        string $eventType,
        Model $actor,
    ): void {
        $address = $migration->standingFundingAddress()->firstOrFail();
        $correction = $eventType === 'funding.standing_address.binding_revision.effective_at_corrected'
            ? StandingFundingAddressBindingEffectiveTimeCorrection::query()
                ->where('standing_funding_address_binding_migration_id', $migration->getKey())
                ->first()
            : null;

        $this->recorder->record(new ExecutionJournalEntryData(
            eventType: $eventType,
            occurredAt: CarbonImmutable::parse(match ($eventType) {
                'funding.standing_address.binding_migration.requested' => $migration->requested_at,
                'funding.standing_address.binding_migration.approved' => $migration->approved_at,
                'funding.standing_address.binding_revision.activated' => $migration->activated_at,
                default => now(),
            }),
            actor: new ExecutionActorData(
                id: (string) $actor->getKey(),
                type: $actor->getMorphClass(),
            ),
            subject: new ExecutionSubjectData(
                id: (string) $migration->getKey(),
                type: 'standing_funding_address_binding_migration',
                display: $migration->reference,
            ),
            references: new ExecutionReferenceData(
                correlationId: 'standing-funding-address:'.$address->reference,
                causationId: $migration->approval_reference ?? $migration->reference,
                executionId: (string) $migration->getKey(),
                externalReference: $migration->reference,
                metadata: [
                    'standing_funding_address_reference' => $address->reference,
                    'binding_revision_reference' => $migration->activatedBindingRevision?->reference,
                ],
            ),
            idempotencyKey: 'x-change:funding-binding:'.$eventType.':'.$migration->getKey(),
            payload: [
                'status' => $migration->status->value,
                'evidence_hash' => $migration->evidence_hash,
                'from_account_reference_hash' => $migration->from_account_reference_hash,
                'to_account_reference_hash' => $migration->to_account_reference_hash,
                'provider_calls' => false,
                'qr_regenerated' => false,
                'provider_inventory_changed' => false,
                ...($correction instanceof StandingFundingAddressBindingEffectiveTimeCorrection ? [
                    'correction_hash' => $correction->correction_hash,
                    'original_effective_at' => $correction->original_effective_at->toRfc3339String(),
                    'corrected_effective_at' => $correction->corrected_effective_at->toRfc3339String(),
                ] : []),
            ],
            metadata: [
                'schema' => 'x-change.funding-standing-address-binding-journal.v1',
                'domain' => 'account_funding',
                'source' => 'governed_binding_migration',
            ],
        ));
    }
}
