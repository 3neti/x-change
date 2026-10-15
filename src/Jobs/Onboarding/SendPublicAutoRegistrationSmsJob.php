<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Jobs\Onboarding;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use LBHurtado\XChange\Actions\Onboarding\SendPublicAutoRegistrationSms;
use LBHurtado\XChange\Models\PublicAutoRegistration;
use LBHurtado\XChange\Queue\Concerns\HasSafeQueueTags;
use Throwable;

final class SendPublicAutoRegistrationSmsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use HasSafeQueueTags;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(public readonly string $registrationReference)
    {
        $this->onQueue((string) config('x-change.redemption.feedback.queue', 'x-change-feedback'));
    }

    public function uniqueId(): string
    {
        return 'public-auto-registration-sms:'.$this->registrationReference;
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->releaseAfter(10)->expireAfter(120)];
    }

    public function handle(SendPublicAutoRegistrationSms $sms): void
    {
        $registration = PublicAutoRegistration::query()
            ->where('reference', $this->registrationReference)
            ->firstOrFail();

        $sms->handle($registration);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Public auto-registration SMS retries exhausted.', [
            'registration_reference' => $this->registrationReference,
            'failure_class' => $exception === null ? null : $exception::class,
        ]);
    }
}
