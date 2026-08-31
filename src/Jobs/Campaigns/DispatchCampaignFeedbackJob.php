<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Jobs\Campaigns;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use LBHurtado\XChange\Actions\Campaigns\DispatchCampaignFeedback;
use LBHurtado\XChange\Actions\Campaigns\RecordCampaignDeliveryAttempt;
use LBHurtado\XChange\Models\CampaignDeliveryAttempt;
use Throwable;

final class DispatchCampaignFeedbackJob implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    public const Queue = 'x-change-feedback';

    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(
        public readonly int $attemptId,
        public readonly string $recipient,
    ) {
        $this->onQueue(self::Queue);
    }

    public function uniqueId(): string
    {
        return 'campaign-feedback:'.$this->attemptId;
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->uniqueId()))
                ->releaseAfter(30)
                ->expireAfter(120),
        ];
    }

    public function handle(DispatchCampaignFeedback $delivery): void
    {
        $deliveryId = $delivery->handle($this->attemptId, $this->recipient);

        if (! is_string($deliveryId) || trim($deliveryId) === '') {
            return;
        }

        ConvergeCampaignFeedbackDeliveryJob::dispatch(
            attemptId: $this->attemptId,
            deliveryId: $deliveryId,
        )->afterCommit();
    }

    public function failed(Throwable $exception): void
    {
        $attempt = CampaignDeliveryAttempt::query()
            ->with('events')
            ->find($this->attemptId);

        if (! $attempt instanceof CampaignDeliveryAttempt
            || $attempt->events->contains(
                fn ($event): bool => in_array($event->event_type, CampaignDeliveryAttempt::TerminalEventTypes, true),
            )) {
            return;
        }

        app(RecordCampaignDeliveryAttempt::class)->append(
            $attempt,
            'failed',
            safeErrorCode: 'feedback_job_exhausted',
            metadata: ['failure_class' => $exception::class],
        );
    }
}
