<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Campaigns;

use Illuminate\Support\Facades\Log;
use LBHurtado\XChange\Actions\Feedback\DeliverAndJournalFeedback;
use LBHurtado\XChange\Models\ProvisionalCoverage;
use LBHurtado\XChange\Services\Feedback\QueuedEngageSparkSmsFeedbackChannelDriver;
use LBHurtado\XChange\Services\Settlement\CampaignDemonstrationProductPresentation;
use LBHurtado\XChange\Services\Settlement\CampaignWalletPayerMobile;
use LBHurtado\XFeedback\Contracts\FeedbackChannelRegistryContract;
use LBHurtado\XFeedback\Data\FeedbackChannelData;
use LBHurtado\XFeedback\Data\FeedbackIntentData;
use LBHurtado\XFeedback\Data\FeedbackMessageData;
use LBHurtado\XFeedback\Data\FeedbackRecipientData;
use RuntimeException;

final readonly class SendCampaignPaymentCompletionSms
{
    public function __construct(
        private DeliverAndJournalFeedback $feedback,
        private FeedbackChannelRegistryContract $channels,
        private CampaignWalletPayerMobile $payerMobile,
        private CampaignDemonstrationProductPresentation $presentations,
    ) {}

    public function handle(ProvisionalCoverage $coverage): void
    {
        $coverage->loadMissing(['recognition.canonicalObservation', 'completionPayCodeIssuance.voucher']);
        $recognition = $coverage->recognition;
        $mobile = $this->payerMobile->resolve($recognition);

        if ($mobile === null) {
            Log::warning('Campaign completion SMS requires a supported wallet mobile source account.', [
                'recognition_reference' => $recognition->reference,
                'reason' => 'unsupported_or_invalid_source_account',
            ]);

            return;
        }

        if (! $this->channels->driver('sms') instanceof QueuedEngageSparkSmsFeedbackChannelDriver) {
            throw new RuntimeException('Campaign completion SMS requires the queued SMS driver.');
        }

        $voucher = $coverage->completionPayCodeIssuance?->voucher;
        if ($voucher === null) {
            throw new RuntimeException('Campaign completion Pay Code is not ready.');
        }
        $presentation = $this->presentations->forDriver($coverage->driver_id, $coverage->driver_version);
        if ($presentation === null) {
            throw new RuntimeException('Campaign completion SMS requires a supported demonstration product.');
        }

        $reference = 'campaign-payment-completion:'.$recognition->reference;
        $url = route('x-change.claim.show', ['code' => $voucher->code]);
        $result = $this->feedback->handle(
            intent: FeedbackIntentData::forEvent(
                key: 'campaign.payment.completion',
                eventType: 'campaign.payment.completion.ready',
                message: new FeedbackMessageData(
                    title: $presentation['first_sms_title'],
                    body: str_replace(':url', $url, $presentation['first_sms_body']),
                ),
                recipients: [new FeedbackRecipientData(
                    type: 'campaign_payer',
                    id: $recognition->reference,
                    phone: $mobile,
                )],
                channels: [new FeedbackChannelData(key: 'sms')],
                source: 'x-change.campaign.payment',
                correlationId: $reference,
                causationId: $recognition->reference,
                subjectType: 'campaign_payment_recognition',
                subjectId: $recognition->reference,
                meta: [
                    'mobile_source' => 'wallet_source_account',
                    'identity_verified_by_x_change' => false,
                    'owns_lifecycle_truth' => false,
                ],
            ),
            channel: 'sms',
            runReference: $reference,
            send: true,
        );

        if (! in_array($result->status, ['queued', 'sent', 'delivered'], true)) {
            throw new RuntimeException('Campaign completion SMS delivery was not accepted.');
        }
    }
}
