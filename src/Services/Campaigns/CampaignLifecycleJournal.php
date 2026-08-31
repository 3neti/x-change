<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Campaigns;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XCampaign\Models\CampaignWorksheet;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;
use LBHurtado\XChange\Models\CampaignDeliveryAttempt;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionMoneyData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

final readonly class CampaignLifecycleJournal
{
    public function __construct(private ExecutionJournalRecorder $recorder) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $metadata
     */
    public function recordWorksheet(
        string $eventType,
        CampaignWorksheet $worksheet,
        mixed $actor,
        array $payload = [],
        array $metadata = [],
    ): ExecutionJournalEntry {
        return $this->record(
            eventType: $eventType,
            subjectId: (string) $worksheet->getKey(),
            subjectDisplay: (string) $worksheet->reference,
            subjectType: 'campaign_worksheet',
            actor: $actor,
            correlationId: 'campaign-worksheet:'.(string) $worksheet->reference,
            executionId: (string) $worksheet->reference,
            idempotencyKey: 'x-change:campaign:'.$eventType.':worksheet:'.$worksheet->getKey().':'.hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            payload: [
                'worksheet_reference' => (string) $worksheet->reference,
                'profile' => (string) $worksheet->profile,
                'fulfillment_mode' => (string) $worksheet->fulfillment_mode,
                ...$payload,
            ],
            metadata: $metadata,
            currency: (string) $worksheet->currency,
            minorAmount: (int) $worksheet->rows()->sum('amount_minor'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $metadata
     */
    public function recordAuthorization(
        string $eventType,
        CampaignWorksheetAuthorization $authorization,
        mixed $actor,
        array $payload = [],
        array $metadata = [],
    ): ExecutionJournalEntry {
        $authorization->loadMissing('worksheet');
        $worksheet = $authorization->worksheet;

        return $this->record(
            eventType: $eventType,
            subjectId: (string) $authorization->getKey(),
            subjectDisplay: (string) $authorization->reference,
            subjectType: 'campaign_authorization',
            actor: $actor,
            correlationId: 'campaign-authorization:'.(string) $authorization->reference,
            causationId: $worksheet instanceof CampaignWorksheet ? (string) $worksheet->reference : null,
            executionId: (string) $authorization->reference,
            idempotencyKey: 'x-change:campaign:'.$eventType.':authorization:'.$authorization->getKey(),
            payload: [
                'authorization_reference' => (string) $authorization->reference,
                'approval_pay_code' => $authorization->approval_pay_code,
                'status' => (string) $authorization->status,
                ...$payload,
            ],
            metadata: $metadata,
            currency: (string) $authorization->currency,
            minorAmount: (int) $authorization->principal_minor,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $metadata
     */
    public function recordFulfillment(
        string $eventType,
        CampaignWorksheetFulfillment $fulfillment,
        mixed $actor,
        array $payload = [],
        array $metadata = [],
    ): ExecutionJournalEntry {
        $fulfillment->loadMissing(['authorization', 'row']);

        return $this->record(
            eventType: $eventType,
            subjectId: (string) $fulfillment->getKey(),
            subjectDisplay: (string) $fulfillment->reference,
            subjectType: 'campaign_fulfillment',
            actor: $actor,
            correlationId: 'campaign-fulfillment:'.(string) $fulfillment->reference,
            causationId: (string) $fulfillment->authorization?->reference,
            executionId: (string) $fulfillment->reference,
            externalReference: $fulfillment->provider_transfer_reference,
            idempotencyKey: 'x-change:campaign:'.$eventType.':fulfillment:'.$fulfillment->getKey().':'.(string) $fulfillment->status,
            payload: [
                'fulfillment_reference' => (string) $fulfillment->reference,
                'mode' => (string) $fulfillment->mode,
                'status' => (string) $fulfillment->status,
                'pay_code' => $fulfillment->pay_code,
                ...$payload,
            ],
            metadata: $metadata,
            currency: (string) $fulfillment->row?->currency,
            minorAmount: (int) ($fulfillment->row?->amount_minor ?? 0),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $metadata
     */
    public function recordDelivery(
        string $eventType,
        CampaignDeliveryAttempt $attempt,
        mixed $actor,
        array $payload = [],
        array $metadata = [],
    ): ExecutionJournalEntry {
        $attempt->loadMissing(['authorization', 'fulfillment.row']);

        return $this->record(
            eventType: $eventType,
            subjectId: (string) $attempt->getKey(),
            subjectDisplay: (string) $attempt->reference,
            subjectType: 'campaign_delivery_attempt',
            actor: $actor,
            correlationId: 'campaign-delivery:'.(string) $attempt->reference,
            causationId: (string) $attempt->authorization?->reference,
            executionId: (string) $attempt->reference,
            idempotencyKey: 'x-change:campaign:'.$eventType.':delivery:'.$attempt->getKey(),
            payload: [
                'delivery_attempt_reference' => (string) $attempt->reference,
                'channel' => (string) $attempt->channel,
                'purpose' => data_get($attempt->metadata, 'purpose'),
                'pay_code' => $attempt->fulfillment?->pay_code,
                ...$payload,
            ],
            metadata: $metadata,
            currency: (string) $attempt->fulfillment?->row?->currency,
            minorAmount: (int) ($attempt->fulfillment?->row?->amount_minor ?? 0),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $metadata
     */
    private function record(
        string $eventType,
        string $subjectId,
        string $subjectDisplay,
        string $subjectType,
        mixed $actor,
        string $correlationId,
        string $executionId,
        string $idempotencyKey,
        array $payload,
        array $metadata,
        ?string $causationId = null,
        ?string $externalReference = null,
        string $currency = 'PHP',
        int $minorAmount = 0,
    ): ExecutionJournalEntry {
        return $this->recorder->record(new ExecutionJournalEntryData(
            eventType: $eventType,
            occurredAt: CarbonImmutable::now('UTC'),
            actor: new ExecutionActorData(
                id: $actor instanceof Model ? (string) $actor->getKey() : 'system',
                type: $actor instanceof Model ? $actor->getMorphClass() : 'system',
            ),
            subject: new ExecutionSubjectData(
                id: $subjectId,
                type: $subjectType,
                display: $subjectDisplay,
            ),
            references: new ExecutionReferenceData(
                correlationId: $correlationId,
                causationId: $causationId,
                executionId: $executionId,
                externalReference: $externalReference,
            ),
            idempotencyKey: $idempotencyKey,
            payload: $payload,
            money: new ExecutionMoneyData(
                currency: mb_strtoupper($currency !== '' ? $currency : 'PHP'),
                minorAmount: max(0, $minorAmount),
            ),
            metadata: [
                'schema' => 'x-change.campaign-lifecycle-journal.v1',
                'domain' => 'campaign',
                'source' => 'cockpit_browser_scenario_runner',
                ...$metadata,
            ],
        ));
    }
}
