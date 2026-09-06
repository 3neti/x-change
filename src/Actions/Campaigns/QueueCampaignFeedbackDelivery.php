<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Campaigns;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XCampaign\Models\CampaignWorksheetFulfillment;
use LBHurtado\XChange\Jobs\Campaigns\DispatchCampaignFeedbackJob;
use LBHurtado\XChange\Models\CampaignDeliveryAttempt;
use LBHurtado\XChange\Services\Campaigns\CampaignLifecycleJournal;

final readonly class QueueCampaignFeedbackDelivery
{
    public function __construct(
        private RecordCampaignDeliveryAttempt $deliveryAttempts,
        private CampaignLifecycleJournal $journal,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        CampaignWorksheetAuthorization $authorization,
        Model $actor,
        string $channel,
        string $recipient,
        string $idempotencyKey,
        string $purpose,
        ?CampaignWorksheetFulfillment $fulfillment = null,
        ?string $retryOfReference = null,
        array $metadata = [],
    ): CampaignDeliveryAttempt {
        $attempt = $this->deliveryAttempts->start(
            authorization: $authorization,
            channel: $channel,
            actor: $actor,
            idempotencyKey: $idempotencyKey,
            fulfillment: $fulfillment,
            recipientRoute: $recipient,
            retryOfReference: $retryOfReference,
            metadata: [
                ...$metadata,
                'purpose' => $purpose,
            ],
        );

        $queue = DispatchCampaignFeedbackJob::Queue;

        $this->deliveryAttempts->append(
            $attempt,
            'queued',
            metadata: ['queue' => $queue],
        );
        $this->journal->recordDelivery($this->queuedEventType($purpose), $attempt, $actor, [
            'queue' => $queue,
        ]);

        DispatchCampaignFeedbackJob::dispatch(
            attemptId: (int) $attempt->getKey(),
            recipient: $recipient,
        )->onQueue($queue)->afterCommit();

        return $attempt;
    }

    private function queuedEventType(string $purpose): string
    {
        return match ($purpose) {
            'officer_authorization' => 'campaign.approval_notice.queued',
            'beneficiary_payout_recovery' => 'campaign.recovery_sms.queued',
            default => 'campaign.delivery.queued',
        };
    }
}
