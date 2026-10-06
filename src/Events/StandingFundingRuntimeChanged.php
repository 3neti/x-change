<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use LBHurtado\XChange\Services\Funding\StandingFundingRuntimeChannel;

final class StandingFundingRuntimeChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable;

    public function __construct(
        private readonly string $eventReference,
        private readonly string $eventType,
        private readonly string $occurredAt,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(app(StandingFundingRuntimeChannel::class)->name());
    }

    public function broadcastAs(): string
    {
        return 'StandingFundingRuntimeChanged';
    }

    /** @return array{schema: string, event_id: string, reason: string, occurred_at: string} */
    public function broadcastWith(): array
    {
        return [
            'schema' => 'x-change.standing-funding-runtime-changed.v1',
            'event_id' => hash('sha256', $this->eventReference),
            'reason' => $this->eventType,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
