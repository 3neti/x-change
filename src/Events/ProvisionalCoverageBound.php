<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use LBHurtado\XChange\Data\Settlement\ProvisionalCoverageBoundData;
use LBHurtado\XChange\Services\Funding\FundingProjectionChannel;

final class ProvisionalCoverageBound implements ShouldBroadcastNow, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable;

    public function __construct(
        private readonly string $ownerType,
        private readonly string $ownerId,
        public readonly ProvisionalCoverageBoundData $coverage,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(app(FundingProjectionChannel::class)->nameForIdentity(
            $this->ownerType,
            $this->ownerId,
        ));
    }

    public function broadcastAs(): string
    {
        return 'ProvisionalCoverageBound';
    }

    /** @return array<string, int|string|null> */
    public function broadcastWith(): array
    {
        return [
            'event_id' => hash_hmac(
                'sha256',
                $this->coverage->coverageReference,
                (string) config('app.key'),
            ),
            ...$this->coverage->toBroadcastArray(),
        ];
    }
}
