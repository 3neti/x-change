<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Jobs\Campaigns;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use LBHurtado\XChange\Actions\Campaigns\ConvergeCampaignFeedbackDelivery;
use Throwable;

final class ConvergeCampaignFeedbackDeliveryJob implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 20;

    public int $timeout = 15;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $attemptId,
        public readonly string $deliveryId,
    ) {
        $this->onQueue(DispatchCampaignFeedbackJob::Queue);
    }

    public function uniqueId(): string
    {
        return 'campaign-feedback-convergence:'.$this->deliveryId;
    }

    public function handle(ConvergeCampaignFeedbackDelivery $convergence): void
    {
        if ($convergence->handle($this->attemptId, $this->deliveryId)) {
            return;
        }

        $this->release(min(60, max(5, $this->attempts() * 5)));
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Campaign feedback outcome did not converge before retries were exhausted.', [
            'attempt_id' => $this->attemptId,
            'delivery_id' => $this->deliveryId,
            'failure_class' => $exception === null ? null : $exception::class,
        ]);
    }
}
