<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PartnerApi;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Models\PartnerApiOperatorAuthorization;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

final readonly class PartnerApiGovernanceJournal
{
    public function __construct(private ExecutionJournalRecorder $recorder) {}

    public function recordClient(PartnerApiClient $client, string $eventType, Model|string $actor): void
    {
        $this->recorder->record(new ExecutionJournalEntryData(
            eventType: $eventType,
            occurredAt: CarbonImmutable::parse($client->updated_at),
            actor: $this->actor($actor),
            subject: new ExecutionSubjectData(
                id: $client->reference,
                type: 'partner_api_client',
                display: $client->name,
            ),
            references: new ExecutionReferenceData(
                correlationId: 'partner-api-client:'.$client->reference,
                causationId: $eventType,
                executionId: (string) $client->getKey(),
                externalReference: $client->reference,
            ),
            idempotencyKey: 'x-change:partner-api-governance:'.$eventType.':'.$client->getKey(),
            payload: [
                'environment' => $client->environment,
                'status' => $client->status->value,
                'scopes' => $client->scopes,
                'mandate' => $client->mandate,
            ],
            metadata: $this->metadata(),
        ));
    }

    public function recordAuthorization(PartnerApiOperatorAuthorization $authorization): void
    {
        $this->recorder->record(new ExecutionJournalEntryData(
            eventType: 'partner_api.operator.authorized',
            occurredAt: CarbonImmutable::parse($authorization->valid_from),
            actor: new ExecutionActorData(
                id: (string) ($authorization->granted_by_id ?? 'system'),
                type: (string) ($authorization->granted_by_type ?? 'commissioning_authority'),
            ),
            subject: new ExecutionSubjectData(
                id: (string) $authorization->operator_id,
                type: $authorization->operator_type,
                display: 'Named Partner API operator',
            ),
            references: new ExecutionReferenceData(
                correlationId: 'partner-api-operator:'.$authorization->operator_type.':'.$authorization->operator_id,
                causationId: $authorization->authorization_reference,
                executionId: (string) $authorization->getKey(),
                externalReference: $authorization->authorization_reference,
            ),
            idempotencyKey: 'x-change:partner-api-governance:operator-authorized:'.$authorization->getKey(),
            payload: [
                'capability' => $authorization->capability,
                'valid_from' => $authorization->valid_from?->toIso8601String(),
                'valid_until' => $authorization->valid_until?->toIso8601String(),
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

    /** @return array<string, mixed> */
    private function metadata(): array
    {
        return [
            'schema' => 'x-change.partner-api-governance-journal.v1',
            'domain' => 'partner_api_governance',
            'canonical_audit_source' => 'x-journal',
        ];
    }
}
