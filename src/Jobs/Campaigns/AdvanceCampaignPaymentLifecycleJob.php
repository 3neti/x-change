<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Jobs\Campaigns;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use LBHurtado\XChange\Actions\Campaigns\SendCampaignPaymentCompletionSms;
use LBHurtado\XChange\Actions\Settlement\AdvanceSettlementCampaignLifecycle;
use LBHurtado\XChange\Jobs\Funding\SyncStandingFundingAddressJob;
use LBHurtado\XChange\Models\CampaignPaymentRecognition;
use Throwable;

final class AdvanceCampaignPaymentLifecycleJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 3600;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(public readonly string $recognitionReference)
    {
        $this->onQueue(SyncStandingFundingAddressJob::Queue);
    }

    public function uniqueId(): string
    {
        return 'campaign-payment-lifecycle:'.$this->recognitionReference;
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->releaseAfter(10)->expireAfter(120)];
    }

    public function handle(AdvanceSettlementCampaignLifecycle $lifecycle, SendCampaignPaymentCompletionSms $sms): void
    {
        $recognition = CampaignPaymentRecognition::query()
            ->where('reference', $this->recognitionReference)
            ->whereNotNull('campaign_payment_qr_binding_id')
            ->firstOrFail();

        $coverage = $lifecycle->handle($recognition);
        if ($coverage !== null) {
            $sms->handle($coverage);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Campaign payment lifecycle retries exhausted.', [
            'recognition_reference' => $this->recognitionReference,
            'failure_class' => $exception === null ? null : $exception::class,
        ]);
    }
}
