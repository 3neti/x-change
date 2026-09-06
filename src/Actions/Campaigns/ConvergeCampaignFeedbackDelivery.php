<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Campaigns;

use LBHurtado\XChange\Models\CampaignDeliveryAttempt;
use LBHurtado\XFeedback\Data\FeedbackDeliveryData;
use LBHurtado\XFeedback\Models\FeedbackDeliveryRecord;

final readonly class ConvergeCampaignFeedbackDelivery
{
    public function __construct(
        private RecordCampaignDeliveryAttempt $deliveryAttempts,
    ) {}

    public function handle(int $attemptId, string $deliveryId): bool
    {
        $attempt = CampaignDeliveryAttempt::query()
            ->with('events')
            ->find($attemptId);

        if (! $attempt instanceof CampaignDeliveryAttempt) {
            return true;
        }

        if ($attempt->events->contains(
            fn ($event): bool => in_array($event->event_type, CampaignDeliveryAttempt::TerminalEventTypes, true),
        )) {
            return true;
        }

        $record = FeedbackDeliveryRecord::query()
            ->where('delivery_id', $deliveryId)
            ->first();

        if (! $record instanceof FeedbackDeliveryRecord) {
            return false;
        }

        if (
            $record->channel !== $attempt->channel
            || $record->causation_id !== $attempt->reference
        ) {
            $this->deliveryAttempts->appendTerminalIfOpen(
                $attempt,
                'failed',
                safeErrorCode: 'feedback_delivery_evidence_mismatch',
            );

            return true;
        }

        if (in_array($record->status, [
            FeedbackDeliveryData::StatusSent,
            FeedbackDeliveryData::StatusDelivered,
        ], true)) {
            $this->deliveryAttempts->appendTerminalIfOpen(
                $attempt,
                'completed',
                providerStatus: $record->provider_status ?? $record->status,
                providerDeliveryReference: $record->provider_message_id,
                metadata: ['feedback_delivery_id' => $record->delivery_id],
            );

            return true;
        }

        if (in_array($record->status, [
            FeedbackDeliveryData::StatusFailedFinal,
            FeedbackDeliveryData::StatusExpired,
            FeedbackDeliveryData::StatusCancelled,
        ], true)) {
            $this->deliveryAttempts->appendTerminalIfOpen(
                $attempt,
                'failed',
                providerStatus: $record->provider_status ?? $record->status,
                providerDeliveryReference: $record->provider_message_id,
                safeErrorCode: 'feedback_delivery_'.$record->status,
                metadata: ['feedback_delivery_id' => $record->delivery_id],
            );

            return true;
        }

        return false;
    }
}
