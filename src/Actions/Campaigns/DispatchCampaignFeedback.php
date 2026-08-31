<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Campaigns;

use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;
use LBHurtado\XChange\Actions\Feedback\DeliverAndJournalFeedback;
use LBHurtado\XChange\Jobs\Campaigns\DispatchCampaignFeedbackJob;
use LBHurtado\XChange\Models\CampaignDeliveryAttempt;
use LBHurtado\XChange\Models\CampaignPayoutRecoveryGrant;
use LBHurtado\XChange\Services\Feedback\QueuedEngageSparkSmsFeedbackChannelDriver;
use LBHurtado\XFeedback\Contracts\FeedbackChannelRegistryContract;
use LBHurtado\XFeedback\Data\FeedbackChannelData;
use LBHurtado\XFeedback\Data\FeedbackDeliveryData;
use LBHurtado\XFeedback\Data\FeedbackIntentData;
use LBHurtado\XFeedback\Data\FeedbackMessageData;
use LBHurtado\XFeedback\Data\FeedbackRecipientData;
use RuntimeException;
use Throwable;

final readonly class DispatchCampaignFeedback
{
    public function __construct(
        private DeliverAndJournalFeedback $feedback,
        private RecordCampaignDeliveryAttempt $deliveryAttempts,
        private FeedbackChannelRegistryContract $feedbackChannels,
    ) {}

    public function handle(int $attemptId, string $recipient): ?string
    {
        $attempt = CampaignDeliveryAttempt::query()
            ->with(['authorization.worksheet', 'fulfillment.row', 'events'])
            ->findOrFail($attemptId);

        if ($attempt->events->contains(
            fn ($event): bool => in_array($event->event_type, ['completed', 'failed'], true),
        )) {
            return null;
        }

        if ($attempt->channel === 'sms' && ! $this->smsQueueBoundaryIsReady()) {
            $this->deliveryAttempts->append(
                $attempt,
                'failed',
                safeErrorCode: 'campaign_sms_queue_boundary_unavailable',
                metadata: ['expected_queue' => DispatchCampaignFeedbackJob::Queue],
            );

            return null;
        }

        $result = $this->feedback->handle(
            intent: $this->intent($attempt, $recipient),
            channel: $attempt->channel,
            runReference: 'campaign-feedback:'.$attempt->reference,
            send: true,
        );

        if ($result->sent) {
            $this->deliveryAttempts->append(
                $attempt,
                'completed',
                providerStatus: $result->providerStatus ?? $result->status,
                providerDeliveryReference: $result->providerMessageId,
                metadata: ['feedback_delivery_id' => $result->deliveryId],
            );

            return null;
        }

        if ($result->status === FeedbackDeliveryData::StatusQueued) {
            $this->deliveryAttempts->append(
                $attempt,
                'provider_queued',
                providerStatus: $result->providerStatus ?? $result->status,
                providerDeliveryReference: $result->providerMessageId,
                metadata: ['feedback_delivery_id' => $result->deliveryId],
            );

            return $result->deliveryId;
        }

        $this->deliveryAttempts->append(
            $attempt,
            'failed',
            providerStatus: $result->providerStatus ?? $result->status,
            providerDeliveryReference: $result->providerMessageId,
            safeErrorCode: 'feedback_delivery_not_accepted',
            metadata: ['feedback_delivery_id' => $result->deliveryId],
        );

        return null;
    }

    private function smsQueueBoundaryIsReady(): bool
    {
        if (config('x-change.redemption.feedback.queue') !== DispatchCampaignFeedbackJob::Queue) {
            return false;
        }

        try {
            return $this->feedbackChannels->driver('sms')
                instanceof QueuedEngageSparkSmsFeedbackChannelDriver;
        } catch (Throwable) {
            return false;
        }
    }

    private function intent(CampaignDeliveryAttempt $attempt, string $recipient): FeedbackIntentData
    {
        $authorization = $attempt->authorization;

        if (! $authorization instanceof CampaignWorksheetAuthorization) {
            throw new RuntimeException('Campaign delivery authorization is unavailable.');
        }

        return match ((string) data_get($attempt->metadata, 'purpose')) {
            'officer_authorization' => $this->officerAuthorizationIntent(
                $authorization,
                $attempt,
                $recipient,
            ),
            'beneficiary_pay_code' => $this->beneficiaryPayCodeIntent(
                $authorization,
                $attempt,
                $recipient,
            ),
            'beneficiary_payout_recovery' => $this->beneficiaryPayoutRecoveryIntent(
                $authorization,
                $attempt,
                $recipient,
            ),
            default => throw new RuntimeException('Campaign feedback purpose is unsupported.'),
        };
    }

    private function officerAuthorizationIntent(
        CampaignWorksheetAuthorization $authorization,
        CampaignDeliveryAttempt $attempt,
        string $recipient,
    ): FeedbackIntentData {
        $claimUrl = route('x-change.claim.show', [
            'code' => $authorization->approval_pay_code,
        ]);
        $worksheetName = (string) ($authorization->worksheet?->name ?? 'Campaign worksheet');

        return FeedbackIntentData::forEvent(
            key: 'campaign.officer_authorization.requested',
            eventType: 'campaign.officer_authorization.delivery.requested',
            message: new FeedbackMessageData(
                title: 'Campaign approval requested',
                body: sprintf(
                    '%s requires officer authorization. Approval Pay Code: %s. Sign in and review it at %s',
                    $worksheetName,
                    $authorization->approval_pay_code,
                    $claimUrl,
                ),
                summary: sprintf('Approval Pay Code %s', $authorization->approval_pay_code),
                actions: [['label' => 'Review Campaign', 'href' => $claimUrl, 'type' => 'link']],
                meta: ['provider_delivery' => true, 'authorization_only' => true],
            ),
            recipients: [$this->recipient($attempt, $recipient, 'campaign_approval_officer')],
            channels: [new FeedbackChannelData(key: $attempt->channel)],
            source: 'x-change.campaigns',
            correlationId: (string) $authorization->reference,
            causationId: (string) $attempt->reference,
            subjectType: 'campaign_worksheet_authorization',
            subjectId: (string) $authorization->reference,
            meta: ['explicit_operator_action' => true],
        );
    }

    private function beneficiaryPayCodeIntent(
        CampaignWorksheetAuthorization $authorization,
        CampaignDeliveryAttempt $attempt,
        string $recipient,
    ): FeedbackIntentData {
        $fulfillment = $attempt->fulfillment;

        if (! $fulfillment instanceof CampaignWorksheetFulfillment) {
            throw new RuntimeException('Campaign beneficiary fulfillment is unavailable.');
        }

        $beneficiary = (array) ($fulfillment->row?->beneficiary_ciphertext ?? []);
        $claimUrl = route('x-change.claim.show', [
            'code' => $fulfillment->pay_code,
        ]);

        return FeedbackIntentData::forEvent(
            key: 'campaign.pay_code.delivery',
            eventType: 'campaign.pay_code.delivery.requested',
            message: new FeedbackMessageData(
                title: 'Your Pay Code is ready',
                body: sprintf('Pay Code %s is ready. Claim it at %s', $fulfillment->pay_code, $claimUrl),
                summary: sprintf('Pay Code %s', $fulfillment->pay_code),
                actions: [['label' => 'Claim Pay Code', 'href' => $claimUrl, 'type' => 'link']],
                meta: ['provider_delivery' => true],
            ),
            recipients: [
                $this->recipient(
                    $attempt,
                    $recipient,
                    'campaign_beneficiary',
                    $this->stringValue($beneficiary['name'] ?? null),
                ),
            ],
            channels: [new FeedbackChannelData(key: $attempt->channel)],
            source: 'x-change.campaigns',
            correlationId: (string) $authorization->reference,
            causationId: (string) $attempt->reference,
            subjectType: 'campaign_worksheet_fulfillment',
            subjectId: (string) $fulfillment->reference,
            meta: ['explicit_operator_action' => true],
        );
    }

    private function beneficiaryPayoutRecoveryIntent(
        CampaignWorksheetAuthorization $authorization,
        CampaignDeliveryAttempt $attempt,
        string $recipient,
    ): FeedbackIntentData {
        $fulfillment = $attempt->fulfillment;
        $hasRecoveryGrant = $fulfillment instanceof CampaignWorksheetFulfillment
            ? CampaignPayoutRecoveryGrant::query()
                ->where('campaign_worksheet_fulfillment_id', $fulfillment->getKey())
                ->whereIn('status', ['available', 'otp_pending', 'verified', 'submitting'])
                ->exists()
            : false;

        if (! $fulfillment instanceof CampaignWorksheetFulfillment
            || $fulfillment->pay_code === null
            || ! $hasRecoveryGrant) {
            throw new RuntimeException('Campaign payout recovery delivery is incomplete.');
        }

        $beneficiary = (array) ($fulfillment->row?->beneficiary_ciphertext ?? []);
        $claimUrl = route('x-change.claim.show', ['code' => $fulfillment->pay_code]);

        return FeedbackIntentData::forEvent(
            key: 'campaign.payout_recovery.delivery',
            eventType: 'campaign.payout_recovery.delivery.requested',
            message: new FeedbackMessageData(
                title: 'Claim your protected Pay Code',
                body: sprintf(
                    'The bank could not complete your payout. Your value remains protected. Claim Pay Code %s at %s',
                    $fulfillment->pay_code,
                    $claimUrl,
                ),
                summary: sprintf('Recover Pay Code %s', $fulfillment->pay_code),
                actions: [['label' => 'Claim Pay Code', 'href' => $claimUrl, 'type' => 'link']],
                meta: ['provider_delivery' => true, 'payout_recovery' => true],
            ),
            recipients: [
                $this->recipient(
                    $attempt,
                    $recipient,
                    'campaign_beneficiary',
                    $this->stringValue($beneficiary['name'] ?? null),
                ),
            ],
            channels: [new FeedbackChannelData(key: $attempt->channel)],
            source: 'x-change.campaigns',
            correlationId: (string) $authorization->reference,
            causationId: (string) $attempt->reference,
            subjectType: 'campaign_worksheet_fulfillment',
            subjectId: (string) $fulfillment->reference,
            meta: [
                'explicit_operator_action' => true,
                'payout_recovery' => true,
                'delivery_contract' => 'campaign.payout-recovery.claim.v2',
            ],
        );
    }

    private function recipient(
        CampaignDeliveryAttempt $attempt,
        string $recipient,
        string $type,
        ?string $name = null,
    ): FeedbackRecipientData {
        return new FeedbackRecipientData(
            type: $type,
            id: (string) $attempt->reference,
            name: $name,
            email: $attempt->channel === 'email' ? $recipient : null,
            phone: $attempt->channel === 'sms' ? $recipient : null,
        );
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
