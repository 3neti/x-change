<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Jobs\Funding;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\EmiCore\Models\WebhookReceipt;
use LBHurtado\XChange\Actions\Funding\FinalizeFundingSuspenseMonitoring;
use LBHurtado\XChange\Actions\Funding\SettleVerifiedFundingIntent;
use LBHurtado\XChange\Actions\Funding\VerifyFundingWebhookReceipt;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Models\FundingIntent;

class VerifyFundingWebhookReceiptJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 120, 300, 900];

    public function __construct(
        public readonly int $webhookReceiptId,
    ) {}

    public function handle(
        VerifyFundingWebhookReceipt $verify,
        SettleVerifiedFundingIntent $settle,
        FinalizeFundingSuspenseMonitoring $finalizeMonitoring,
    ): void {
        $receipt = WebhookReceipt::query()->findOrFail($this->webhookReceiptId);

        $verify->handle($receipt);

        $observationIds = ProviderFundingObservation::query()
            ->where('webhook_receipt_id', $receipt->getKey())
            ->pluck('id');

        if ($observationIds->isNotEmpty()) {
            FundingIntent::query()
                ->where('status', FundingIntentStatus::Verified)
                ->whereIn('matched_observation_id', $observationIds)
                ->eachById(fn (FundingIntent $intent) => $settle->handle($intent));
        }

        $finalizeMonitoring->handle($receipt->getKey());
    }
}
