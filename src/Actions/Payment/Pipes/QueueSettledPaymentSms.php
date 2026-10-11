<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment\Pipes;

use Closure;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Actions\Feedback\DeliverAndJournalFeedback;
use LBHurtado\XChange\Models\Checkout;
use LBHurtado\XChange\Models\FundingSettlement;
use LBHurtado\XChange\Models\SettledPaymentProcess;
use LBHurtado\XChange\Services\Settlement\CampaignWalletPayerMobile;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use LBHurtado\XFeedback\Data\FeedbackChannelData;
use LBHurtado\XFeedback\Data\FeedbackIntentData;
use LBHurtado\XFeedback\Data\FeedbackMessageData;
use LBHurtado\XFeedback\Data\FeedbackRecipientData;
use LBHurtado\XFeedback\Services\PaymentSmsContent;

final readonly class QueueSettledPaymentSms
{
    public function __construct(
        private DeliverAndJournalFeedback $feedback,
        private PaymentSmsContent $content,
        private CampaignWalletPayerMobile $walletPayerMobile,
    ) {}

    public function handle(SettledPaymentProcess $process, Closure $next): mixed
    {
        if ($process->flow === 'campaign_qr_ph') {
            $process->forceFill([
                'sms_status' => $process->sms_enabled_snapshot ? 'awaiting_completion_link' : 'skipped',
            ])->save();

            return $next($process);
        }

        if (! $process->sms_enabled_snapshot || $process->contact_id === null) {
            $process->forceFill(['sms_status' => 'skipped'])->save();

            return $next($process);
        }

        $observation = ProviderFundingObservation::query()->findOrFail($process->observation_id);
        if ($this->walletPayerMobile->fromObservation($observation) === null
            && ! $this->hasReconciledProviderPayer($process)) {
            $process->forceFill(['sms_status' => 'skipped'])->save();

            return $next($process);
        }

        $contact = Contact::query()->findOrFail($process->contact_id);
        $mobile = MobileNumber::normalize((string) $contact->mobile);

        if ($mobile === null) {
            $process->forceFill(['sms_status' => 'skipped'])->save();

            return $next($process);
        }

        $reference = "payment-settled:{$process->payment_type}:{$process->payment_id}";
        $result = $this->feedback->handle(
            intent: FeedbackIntentData::forEvent(
                key: 'payment.settled',
                eventType: 'payment.settled',
                message: new FeedbackMessageData(
                    title: 'Payment received',
                    body: $this->content->generic((int) $process->gross_amount_minor),
                ),
                recipients: [new FeedbackRecipientData(type: 'payment_payer', id: (string) $process->getKey(), phone: $mobile)],
                channels: [new FeedbackChannelData(key: 'sms')],
                source: 'x-change.payment',
                correlationId: $reference,
                causationId: $reference,
                subjectType: $process->payment_type,
                subjectId: (string) $process->payment_id,
            ),
            channel: 'sms',
            runReference: $reference,
            send: true,
        );
        $process->forceFill(['sms_status' => $result->status])->save();

        return $next($process);
    }

    private function hasReconciledProviderPayer(SettledPaymentProcess $process): bool
    {
        if ($process->flow !== 'public_auto_generate' || $process->payment_type !== 'funding_settlement') {
            return false;
        }

        $settlement = FundingSettlement::query()->find($process->payment_id);
        $checkout = $settlement === null ? null : Checkout::query()
            ->whereHas('fundingOrder', fn ($query) => $query->where('funding_intent_id', $settlement->funding_intent_id))
            ->first();

        return $checkout !== null
            && $checkout->selected_method === 'qr_ph'
            && (int) $checkout->contact_id === (int) $process->contact_id
            && in_array($checkout->contact_source, ['provider_reported', 'provider_verified'], true)
            && $checkout->events()->where('event_type', 'contact_association_reconciled')->exists();
    }
}
