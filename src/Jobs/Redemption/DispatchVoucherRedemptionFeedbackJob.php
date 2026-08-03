<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Jobs\Redemption;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use LBHurtado\XChange\Actions\Redemption\DispatchVoucherRedemptionFeedback;
use Throwable;

final class DispatchVoucherRedemptionFeedbackJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 600;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(
        public readonly int $voucherClaimId,
        public readonly ?string $outcomeFingerprint = null,
    ) {
        $this->onQueue((string) config(
            'x-change.redemption.feedback.queue',
            'x-change-feedback',
        ));
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->uniqueId()))
                ->releaseAfter(10)
                ->expireAfter($this->uniqueFor)
                ->shared(),
        ];
    }

    public function uniqueId(): string
    {
        return implode(':', [
            'voucher-redemption-feedback',
            (string) $this->voucherClaimId,
            $this->outcomeFingerprint ?? 'claim-recorded',
        ]);
    }

    public function handle(DispatchVoucherRedemptionFeedback $dispatch): void
    {
        $dispatch->handle($this->voucherClaimId);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Voucher redemption feedback delivery exhausted its retries.', [
            'voucher_claim_id' => $this->voucherClaimId,
            'failure_class' => $exception::class,
        ]);
    }
}
