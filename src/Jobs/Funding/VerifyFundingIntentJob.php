<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Jobs\Funding;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use LBHurtado\XChange\Actions\Funding\SettleVerifiedFundingIntent;
use LBHurtado\XChange\Actions\Funding\VerifyFundingIntent;
use LBHurtado\XChange\Data\Funding\FundingIntentVerificationData;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Enums\FundingVerificationTrigger;
use LBHurtado\XChange\Models\FundingIntent;

class VerifyFundingIntentJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $uniqueFor;

    /** @var list<int> */
    public array $backoff = [30, 120, 300, 900];

    public function __construct(
        public readonly int $fundingIntentId,
        public readonly string $providerCode,
        public readonly FundingVerificationTrigger $trigger,
        public readonly string $actorId,
        public readonly ?int $webhookReceiptId = null,
    ) {
        $this->uniqueFor = (int) config('x-change.funding.verification_lock_seconds', 120);
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->uniqueId()))
                ->releaseAfter(5)
                ->expireAfter($this->uniqueFor)
                ->shared(),
            new RateLimited('x-change-funding-verification'),
        ];
    }

    public function uniqueId(): string
    {
        return 'funding-intent:'.$this->fundingIntentId;
    }

    public function handle(
        VerifyFundingIntent $verify,
        SettleVerifiedFundingIntent $settle,
    ): void {
        $intent = FundingIntent::query()->findOrFail($this->fundingIntentId);

        if ($intent->provider_code !== $this->providerCode) {
            return;
        }

        $intent = $verify->handle($intent, new FundingIntentVerificationData(
            trigger: $this->trigger,
            actorId: $this->actorId,
            webhookReceiptId: $this->webhookReceiptId,
        ));

        if ($intent->status === FundingIntentStatus::Verified) {
            $settle->handle($intent);
        }
    }
}
