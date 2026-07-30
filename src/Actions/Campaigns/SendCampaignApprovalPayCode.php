<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Campaigns;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XCampaign\Models\CampaignWorksheetAuthorization;
use LBHurtado\XChange\Models\CampaignDeliveryAttempt;
use RuntimeException;

final readonly class SendCampaignApprovalPayCode
{
    public function __construct(private QueueCampaignFeedbackDelivery $delivery) {}

    public function handle(
        CampaignWorksheetAuthorization $authorization,
        Model $actor,
        string $channel,
        string $recipient,
        string $requestToken,
    ): string {
        $this->assertReady($authorization, $channel);
        $idempotencyKey = implode(':', [
            'campaign-approval-delivery',
            (string) $authorization->reference,
            $channel,
            $requestToken,
        ]);

        if (CampaignDeliveryAttempt::query()
            ->where('idempotency_key_hash', hash('sha256', $idempotencyKey))
            ->exists()) {
            return 'already_requested';
        }

        $this->delivery->handle(
            authorization: $authorization,
            actor: $actor,
            channel: $channel,
            recipient: $recipient,
            idempotencyKey: $idempotencyKey,
            purpose: 'officer_authorization',
            metadata: [
                'pay_code' => $authorization->approval_pay_code,
                'recipient_type' => 'campaign_approval_officer',
            ],
        );

        return 'queued';
    }

    private function assertReady(CampaignWorksheetAuthorization $authorization, string $channel): void
    {
        if (! in_array($channel, ['sms', 'email'], true)) {
            throw new RuntimeException('Campaign approval delivery channel is not supported.');
        }

        if ($authorization->status !== 'awaiting_officer' || blank($authorization->approval_pay_code)) {
            throw new RuntimeException('Campaign approval Pay Code is not awaiting an officer.');
        }
    }
}
