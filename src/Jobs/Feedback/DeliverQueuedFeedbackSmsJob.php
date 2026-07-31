<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Jobs\Feedback;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use LBHurtado\XChange\Actions\Feedback\CompleteQueuedFeedbackSmsDelivery;
use Throwable;

final class DeliverQueuedFeedbackSmsJob implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public int $uniqueFor = 3600;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(
        public readonly string $deliveryId,
        public readonly string $message,
        public readonly string $sender,
    ) {
        $this->onQueue((string) config(
            'x-change.redemption.feedback.queue',
            'x-change-feedback',
        ));
    }

    public function uniqueId(): string
    {
        return 'feedback-sms:'.$this->deliveryId;
    }

    public function handle(CompleteQueuedFeedbackSmsDelivery $delivery): void
    {
        $delivery->handle(
            deliveryId: $this->deliveryId,
            message: $this->message,
            sender: $this->sender,
        );
    }

    public function failed(Throwable $exception): void
    {
        try {
            app(CompleteQueuedFeedbackSmsDelivery::class)->fail(
                deliveryId: $this->deliveryId,
                exception: $exception,
            );
        } catch (Throwable $recordingException) {
            report($recordingException);
        }

        Log::error('Queued feedback SMS delivery exhausted its retries.', [
            'delivery_id' => $this->deliveryId,
            'failure_class' => $exception::class,
        ]);
    }
}
