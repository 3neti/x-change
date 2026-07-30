<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Campaigns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Models\CampaignDeliveryAttempt;
use LBHurtado\XChange\Models\CampaignDeliveryAttemptEvent;

final class RecordCampaignDeliveryAttempt
{
    public function __construct(private readonly AuditLoggerContract $audit) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function start(
        CampaignWorksheetAuthorization $authorization,
        string $channel,
        mixed $actor,
        string $idempotencyKey,
        ?CampaignWorksheetFulfillment $fulfillment = null,
        ?string $recipientRoute = null,
        ?string $retryOfReference = null,
        array $metadata = [],
    ): CampaignDeliveryAttempt {
        $attempt = DB::transaction(function () use ($authorization, $channel, $actor, $idempotencyKey, $fulfillment, $recipientRoute, $retryOfReference, $metadata): CampaignDeliveryAttempt {
            $attemptNumber = CampaignDeliveryAttempt::query()
                ->where('campaign_worksheet_authorization_id', $authorization->getKey())
                ->where('channel', $channel)
                ->lockForUpdate()
                ->max('attempt_number');

            $attempt = CampaignDeliveryAttempt::query()->create([
                'campaign_worksheet_authorization_id' => $authorization->getKey(),
                'campaign_worksheet_fulfillment_id' => $fulfillment?->getKey(),
                'channel' => $channel,
                'attempt_number' => ((int) $attemptNumber) + 1,
                'idempotency_key_hash' => hash('sha256', $idempotencyKey),
                'retry_of_reference' => $retryOfReference,
                'requested_by_type' => $actor instanceof Model ? $actor->getMorphClass() : $actor::class,
                'requested_by_id' => (string) $actor->getAuthIdentifier(),
                'recipient_route_hash' => $recipientRoute === null ? null : hash('sha256', mb_strtolower(trim($recipientRoute))),
                'metadata' => $metadata,
                'requested_at' => now(),
            ]);

            $this->append($attempt, 'requested', metadata: $metadata);

            return $attempt;
        });

        $this->audit->log('campaign.delivery.requested', $this->auditPayload($attempt));

        return $attempt;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function append(
        CampaignDeliveryAttempt $attempt,
        string $eventType,
        ?string $providerStatus = null,
        ?string $providerDeliveryReference = null,
        ?string $safeErrorCode = null,
        array $metadata = [],
    ): CampaignDeliveryAttemptEvent {
        $event = DB::transaction(function () use ($attempt, $eventType, $providerStatus, $providerDeliveryReference, $safeErrorCode, $metadata): CampaignDeliveryAttemptEvent {
            $sequence = (int) $attempt->events()->lockForUpdate()->max('sequence') + 1;

            return $attempt->events()->create([
                'sequence' => $sequence,
                'event_type' => $eventType,
                'provider_status' => $providerStatus,
                'provider_delivery_reference' => $providerDeliveryReference,
                'safe_error_code' => $safeErrorCode,
                'metadata' => $metadata,
                'occurred_at' => now(),
            ]);
        });

        if ($eventType !== 'requested') {
            $this->audit->log('campaign.delivery.'.$eventType, [
                ...$this->auditPayload($attempt),
                'delivery_event_reference' => (string) $event->reference,
                'safe_error_code' => $safeErrorCode,
            ]);
        }

        return $event;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function appendTerminalIfOpen(
        CampaignDeliveryAttempt $attempt,
        string $eventType,
        ?string $providerStatus = null,
        ?string $providerDeliveryReference = null,
        ?string $safeErrorCode = null,
        array $metadata = [],
    ): CampaignDeliveryAttemptEvent {
        if (! in_array($eventType, ['completed', 'failed'], true)) {
            throw new InvalidArgumentException('A terminal campaign delivery event must be completed or failed.');
        }

        [$event, $created] = DB::transaction(function () use ($attempt, $eventType, $providerStatus, $providerDeliveryReference, $safeErrorCode, $metadata): array {
            $lockedAttempt = CampaignDeliveryAttempt::query()
                ->lockForUpdate()
                ->findOrFail($attempt->getKey());
            $existing = $lockedAttempt->events()
                ->whereIn('event_type', ['completed', 'failed'])
                ->orderBy('sequence')
                ->first();

            if ($existing instanceof CampaignDeliveryAttemptEvent) {
                return [$existing, false];
            }

            $sequence = (int) $lockedAttempt->events()
                ->lockForUpdate()
                ->max('sequence') + 1;
            $event = $lockedAttempt->events()->create([
                'sequence' => $sequence,
                'event_type' => $eventType,
                'provider_status' => $providerStatus,
                'provider_delivery_reference' => $providerDeliveryReference,
                'safe_error_code' => $safeErrorCode,
                'metadata' => $metadata,
                'occurred_at' => now(),
            ]);

            return [$event, true];
        });

        if ($created) {
            $this->audit->log('campaign.delivery.'.$eventType, [
                ...$this->auditPayload($attempt),
                'delivery_event_reference' => (string) $event->reference,
                'safe_error_code' => $safeErrorCode,
            ]);
        }

        return $event;
    }

    /** @return array<string, mixed> */
    private function auditPayload(CampaignDeliveryAttempt $attempt): array
    {
        return [
            'resource_type' => 'campaign_delivery_attempt',
            'resource_id' => (string) $attempt->reference,
            'correlation_id' => (string) $attempt->authorization?->reference,
            'channel' => $attempt->channel,
            'attempt_number' => $attempt->attempt_number,
            'retry_of_reference' => $attempt->retry_of_reference,
        ];
    }
}
