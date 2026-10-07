<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Jobs\Funding;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use LBHurtado\XChange\Actions\Funding\SyncStandingFundingAddress;
use LBHurtado\XChange\Actions\Operations\RecordExternalJobFailure;
use LBHurtado\XChange\Enums\FundingAddressStatus;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LBHurtado\XChange\Queue\Concerns\HasSafeQueueTags;
use LBHurtado\XChange\Services\Funding\StandingFundingSyncRuntime;
use Throwable;

final class SyncStandingFundingAddressJob implements ShouldQueue
{
    use Dispatchable;
    use HasSafeQueueTags;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public const string Queue = 'x-change-funding';

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    /** @var list<int> */
    public array $backoff = [30, 120, 300, 900];

    public function __construct(
        public readonly int $standingFundingAddressId,
        public readonly string $providerCode,
        public readonly string $trigger,
        public readonly ?int $webhookReceiptId = null,
        public readonly ?int $runtimeGeneration = null,
        public readonly ?string $runReference = null,
        public readonly ?string $leaseToken = null,
    ) {
        $this->onQueue(self::Queue);
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            new RateLimited('x-change-funding-verification'),
        ];
    }

    public function handle(SyncStandingFundingAddress $sync, StandingFundingSyncRuntime $runtime): void
    {
        if ($this->runtimeGeneration === null || $this->runReference === null || $this->leaseToken === null
            || ! $runtime->start($this->runReference, $this->leaseToken, $this->runtimeGeneration)) {
            return;
        }

        $address = StandingFundingAddress::query()->findOrFail($this->standingFundingAddressId);

        if ($address->provider_code !== strtolower(trim($this->providerCode))
            || $address->status !== FundingAddressStatus::Active) {
            $runtime->succeed($this->runReference, ['outcome' => 'address_inactive']);

            return;
        }

        try {
            $result = $sync->handle($address, $this->trigger, $this->webhookReceiptId);
            $runtime->succeed($this->runReference, [
                'observed' => $result->observed,
                'settled' => $result->settled,
                'awaiting_approval' => $result->awaitingApproval,
                'suspense' => $result->suspense,
                'applied' => $result->applied,
                'recognized' => $result->recognized,
            ]);
        } catch (Throwable $failure) {
            $runtime->fail($this->runReference, $failure);

            throw $failure;
        }
    }

    public function failed(Throwable $exception): void
    {
        try {
            app(RecordExternalJobFailure::class)->handle(
                jobType: class_basename(self::class),
                subjectType: 'standing_funding_address',
                subjectId: $this->standingFundingAddressId,
                failure: $exception,
                providerCode: $this->providerCode,
                trigger: $this->trigger,
                metadata: ['run_reference' => $this->runReference],
            );
        } catch (Throwable $recordingFailure) {
            try {
                report($recordingFailure);
            } catch (Throwable) {
            }
        }
    }
}
