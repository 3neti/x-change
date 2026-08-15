<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Jobs\Provisioning;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use LBHurtado\XChange\Actions\Feedback\DeliverAndJournalFeedback;
use LBHurtado\XFeedback\Data\FeedbackChannelData;
use LBHurtado\XFeedback\Data\FeedbackIntentData;
use LBHurtado\XFeedback\Data\FeedbackMessageData;
use LBHurtado\XFeedback\Data\FeedbackRecipientData;
use LBHurtado\XProvisioning\Models\ProvisioningOffer;

final class DeliverProvisioningOfferJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $offerReference,
        public readonly string $channel,
        public readonly string $recipient,
        public readonly string $encryptedClaimToken,
    ) {
        $this->onQueue('x-change-feedback');
    }

    public function handle(DeliverAndJournalFeedback $feedback): void
    {
        $offer = ProvisioningOffer::query()->where('reference', $this->offerReference)->firstOrFail();
        $claimUrl = route('x-change.provisioning.claim.show', ['token' => Crypt::decryptString($this->encryptedClaimToken)]);
        $feedback->handle(
            intent: FeedbackIntentData::forEvent(
                key: 'provisioning.offer.delivery',
                eventType: 'provisioning.offer.delivery.requested',
                message: new FeedbackMessageData(
                    title: 'Your X-Change invitation is ready',
                    body: sprintf('Accept your governed X-Change invitation at %s', $claimUrl),
                    summary: 'Governed X-Change invitation',
                    actions: [['label' => 'Review Invitation', 'href' => $claimUrl, 'type' => 'link']],
                    meta: ['provider_delivery' => true, 'authority_invitation' => true],
                ),
                recipients: [new FeedbackRecipientData(
                    type: 'provisioning_candidate',
                    id: $offer->reference,
                    email: $this->channel === 'email' ? $this->recipient : null,
                    phone: $this->channel === 'sms' ? $this->recipient : null,
                )],
                channels: [new FeedbackChannelData(key: $this->channel)],
                source: 'x-change.provisioning',
                correlationId: $offer->reference,
                causationId: 'provisioning-delivery:'.$offer->reference.':'.$this->channel,
                subjectType: 'provisioning_offer',
                subjectId: $offer->reference,
                meta: ['explicit_operator_action' => true],
            ),
            channel: $this->channel,
            runReference: 'provisioning-delivery:'.$offer->reference.':'.$this->channel,
            send: true,
        );
    }
}
