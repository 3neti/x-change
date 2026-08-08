<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Models\CommercialOffering;
use LBHurtado\XChange\Models\CommercialOfferingActivation;
use LBHurtado\XChange\Models\CommercialOperatorAuthorization;
use LBHurtado\XChange\Models\CommercialPartnerDestinationRevision;
use LBHurtado\XChange\Models\CommercialPartnerRevision;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

final readonly class CommercialGovernanceJournal
{
    public function __construct(private ExecutionJournalRecorder $recorder) {}

    public function recordOffering(
        CommercialOffering $offering,
        string $eventType,
        Model|string $actor,
        string $authorityReference,
    ): void {
        $this->recorder->record(new ExecutionJournalEntryData(
            eventType: $eventType,
            occurredAt: match ($eventType) {
                'commercial.offering.submitted' => CarbonImmutable::parse($offering->submitted_at),
                'commercial.offering.published' => CarbonImmutable::parse($offering->approved_at),
                default => CarbonImmutable::parse($offering->created_at),
            },
            actor: $this->actor($actor),
            subject: new ExecutionSubjectData(
                id: (string) $offering->getKey(),
                type: 'commercial_offering',
                display: $offering->reference.'@'.$offering->version,
            ),
            references: new ExecutionReferenceData(
                correlationId: 'commercial-offering:'.$offering->profile.':'.$offering->reference,
                causationId: $authorityReference,
                executionId: (string) $offering->getKey(),
                externalReference: $authorityReference,
                metadata: ['profile' => $offering->profile, 'snapshot_hash' => $offering->snapshot_hash],
            ),
            idempotencyKey: 'x-change:commercial-governance:'.$eventType.':'.$offering->getKey(),
            payload: [
                'status' => $offering->status->value,
                'origin' => $offering->origin->value,
                'version' => $offering->version,
                'snapshot_hash' => $offering->snapshot_hash,
            ],
            metadata: $this->metadata(),
        ));
    }

    public function recordActivation(CommercialOfferingActivation $activation): void
    {
        $this->recorder->record(new ExecutionJournalEntryData(
            eventType: 'commercial.offering.activated',
            occurredAt: CarbonImmutable::parse($activation->activated_at),
            actor: new ExecutionActorData(
                id: $activation->authority->value,
                type: 'commercial_activation_authority',
            ),
            subject: new ExecutionSubjectData(
                id: (string) $activation->commercial_offering_id,
                type: 'commercial_offering',
                display: $activation->offering_reference.'@'.$activation->offering_version,
            ),
            references: new ExecutionReferenceData(
                correlationId: 'commercial-offering:'.$activation->profile.':'.$activation->offering_reference,
                causationId: $activation->activation_reference,
                executionId: (string) $activation->getKey(),
                externalReference: $activation->activation_reference,
                metadata: ['snapshot_hash' => $activation->snapshot_hash],
            ),
            idempotencyKey: 'x-change:commercial-governance:activated:'.$activation->getKey(),
            payload: [
                'profile' => $activation->profile,
                'origin' => $activation->origin->value,
                'authority' => $activation->authority->value,
                'version' => $activation->offering_version,
                'snapshot_hash' => $activation->snapshot_hash,
            ],
            metadata: $this->metadata(),
        ));
    }

    public function recordAuthorization(CommercialOperatorAuthorization $authorization): void
    {
        $this->recorder->record(new ExecutionJournalEntryData(
            eventType: 'commercial.operator.authorized',
            occurredAt: CarbonImmutable::parse($authorization->valid_from),
            actor: new ExecutionActorData(
                id: (string) ($authorization->granted_by_id ?? 'system'),
                type: (string) ($authorization->granted_by_type ?? 'commissioning_authority'),
            ),
            subject: new ExecutionSubjectData(
                id: (string) $authorization->operator_id,
                type: $authorization->operator_type,
                display: 'Named Commercial operator',
            ),
            references: new ExecutionReferenceData(
                correlationId: 'commercial-operator:'.$authorization->operator_type.':'.$authorization->operator_id,
                causationId: $authorization->authorization_reference,
                executionId: (string) $authorization->getKey(),
                externalReference: $authorization->authorization_reference,
            ),
            idempotencyKey: 'x-change:commercial-governance:operator-authorized:'.$authorization->getKey(),
            payload: [
                'capability' => $authorization->capability,
                'valid_from' => $authorization->valid_from?->toIso8601String(),
                'valid_until' => $authorization->valid_until?->toIso8601String(),
            ],
            metadata: $this->metadata(),
        ));
    }

    public function recordPartner(
        CommercialPartnerRevision $revision,
        string $eventType,
        Model $actor,
    ): void {
        $this->recorder->record(new ExecutionJournalEntryData(
            eventType: $eventType,
            occurredAt: CarbonImmutable::parse(
                $revision->approved_at ?? $revision->submitted_at ?? $revision->created_at,
            ),
            actor: $this->actor($actor),
            subject: new ExecutionSubjectData(
                id: $revision->partner->reference,
                type: 'commercial_partner',
                display: $revision->display_name,
            ),
            references: new ExecutionReferenceData(
                correlationId: 'commercial-partner:'.$revision->partner->reference,
                causationId: $revision->authorization_reference,
                executionId: (string) $revision->getKey(),
                externalReference: $revision->authorization_reference,
                metadata: ['snapshot_hash' => $revision->snapshot_hash],
            ),
            idempotencyKey: 'x-change:commercial-governance:'.$eventType.':'.$revision->getKey(),
            payload: [
                'status' => $revision->status->value,
                'version' => $revision->version,
                'attribution_basis' => $revision->attribution_basis,
                'snapshot_hash' => $revision->snapshot_hash,
            ],
            metadata: $this->metadata(),
        ));
    }

    public function recordPartnerDestination(
        CommercialPartnerDestinationRevision $revision,
        string $eventType,
        Model $actor,
    ): void {
        $this->recorder->record(new ExecutionJournalEntryData(
            eventType: $eventType,
            occurredAt: CarbonImmutable::parse(
                $revision->approved_at ?? $revision->submitted_at ?? $revision->created_at,
            ),
            actor: $this->actor($actor),
            subject: new ExecutionSubjectData(
                id: $revision->partner->reference,
                type: 'commercial_partner_destination',
                display: $revision->destination_summary,
            ),
            references: new ExecutionReferenceData(
                correlationId: 'commercial-partner-destination:'.$revision->partner->reference,
                causationId: $revision->authorization_reference,
                executionId: (string) $revision->getKey(),
                externalReference: $revision->authorization_reference,
                metadata: ['destination_hash' => $revision->destination_hash],
            ),
            idempotencyKey: 'x-change:commercial-governance:'.$eventType.':'.$revision->getKey(),
            payload: [
                'status' => $revision->status->value,
                'version' => $revision->version,
                'provider' => $revision->provider,
                'connection_reference' => $revision->connection_reference,
                'currency' => $revision->currency,
                'destination_summary' => $revision->destination_summary,
            ],
            metadata: $this->metadata(),
        ));
    }

    private function actor(Model|string $actor): ExecutionActorData
    {
        return $actor instanceof Model
            ? new ExecutionActorData(id: (string) $actor->getKey(), type: $actor->getMorphClass())
            : new ExecutionActorData(id: $actor, type: 'commissioning_authority');
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(): array
    {
        return [
            'schema' => 'x-change.commercial-governance-journal.v1',
            'domain' => 'commercial_governance',
            'canonical_audit_source' => 'x-journal',
        ];
    }
}
