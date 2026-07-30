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
use LBHurtado\XChange\Actions\Funding\SyncStandingFundingAddress;
use LBHurtado\XChange\Enums\FundingAddressStatus;
use LBHurtado\XChange\Models\StandingFundingAddress;

final class SyncStandingFundingAddressJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public int $uniqueFor;

    /** @var list<int> */
    public array $backoff = [30, 120, 300, 900];

    public function __construct(
        public readonly int $standingFundingAddressId,
        public readonly string $providerCode,
        public readonly string $trigger,
        public readonly ?int $webhookReceiptId = null,
    ) {
        $this->uniqueFor = max(
            1,
            (int) config('x-change.funding.standing_addresses.lock_seconds', 120),
        );
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
        return 'standing-funding-address:'.$this->standingFundingAddressId;
    }

    public function handle(SyncStandingFundingAddress $sync): void
    {
        $address = StandingFundingAddress::query()->findOrFail($this->standingFundingAddressId);

        if ($address->provider_code !== strtolower(trim($this->providerCode))
            || $address->status !== FundingAddressStatus::Active) {
            return;
        }

        $sync->handle($address, $this->trigger, $this->webhookReceiptId);
    }
}
