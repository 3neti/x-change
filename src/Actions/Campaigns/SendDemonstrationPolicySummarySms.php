<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Campaigns;

use Illuminate\Support\Facades\Log;
use LBHurtado\XChange\Actions\Feedback\DeliverAndJournalFeedback;
use LBHurtado\XChange\Models\PolicyCompletionOutcome;
use LBHurtado\XChange\Services\Feedback\QueuedEngageSparkSmsFeedbackChannelDriver;
use LBHurtado\XChange\Services\Settlement\CampaignWalletPayerMobile;
use LBHurtado\XChange\Services\Settlement\DemonstrationPolicySummary;
use LBHurtado\XFeedback\Contracts\FeedbackChannelRegistryContract;
use LBHurtado\XFeedback\Data\FeedbackChannelData;
use LBHurtado\XFeedback\Data\FeedbackIntentData;
use LBHurtado\XFeedback\Data\FeedbackMessageData;
use LBHurtado\XFeedback\Data\FeedbackRecipientData;
use RuntimeException;

final readonly class SendDemonstrationPolicySummarySms
{
    public function __construct(
        private DemonstrationPolicySummary $summaries,
        private CampaignWalletPayerMobile $payerMobile,
        private DeliverAndJournalFeedback $feedback,
        private FeedbackChannelRegistryContract $channels,
    ) {}

    public function handle(PolicyCompletionOutcome $outcome): void
    {
        if (! config('x-change.settlement.policy_completion.demonstration_summary.sms_enabled', false)) {
            return;
        }
        $url = $this->summaries->url($outcome);
        if ($url === null) {
            return;
        }
        $recognition = $outcome->request->projection->issuance->coverage->recognition;
        $mobile = $this->payerMobile->resolve($recognition);
        if ($mobile === null) {
            Log::warning('Demonstration policy summary SMS has no supported wallet mobile destination.', [
                'outcome_reference' => $outcome->reference,
            ]);

            return;
        }
        if (! $this->channels->driver('sms') instanceof QueuedEngageSparkSmsFeedbackChannelDriver) {
            throw new RuntimeException('Demonstration policy summary SMS requires the queued SMS driver.');
        }

        $reference = 'campaign-demo-policy:'.$outcome->reference;
        $result = $this->feedback->handle(
            intent: FeedbackIntentData::forEvent(
                key: 'campaign.demo_policy.summary',
                eventType: 'campaign.demo_policy.summary.ready',
                message: new FeedbackMessageData(
                    title: 'Demonstration policy summary',
                    body: 'Your demo policy summary is ready. DEMONSTRATION ONLY, not an issued insurance policy or proof of coverage. View: '.$url,
                ),
                recipients: [new FeedbackRecipientData(type: 'campaign_payer', id: $recognition->reference, phone: $mobile)],
                channels: [new FeedbackChannelData(key: 'sms')],
                source: 'x-change.campaign.demo_policy',
                correlationId: $reference,
                causationId: $outcome->reference,
                subjectType: 'policy_completion_outcome',
                subjectId: $outcome->reference,
                meta: ['demonstration_only' => true, 'mobile_source' => 'wallet_source_account', 'owns_lifecycle_truth' => false],
            ),
            channel: 'sms',
            runReference: $reference,
            send: true,
        );
        if (! in_array($result->status, ['queued', 'sent', 'delivered'], true)) {
            throw new RuntimeException('Demonstration policy summary SMS delivery was not accepted.');
        }
    }
}
