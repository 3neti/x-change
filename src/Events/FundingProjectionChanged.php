<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LBHurtado\XChange\Services\Funding\FundingProjectionChannel;

final class FundingProjectionChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        private readonly string $ownerType,
        private readonly string $ownerId,
        private readonly string $receiptReference,
        private readonly string $occurredAt,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(
            app(FundingProjectionChannel::class)->nameForIdentity(
                $this->ownerType,
                $this->ownerId,
            ),
        );
    }

    public function broadcastAs(): string
    {
        return 'FundingProjectionChanged';
    }

    /**
     * @return array{schema: string, event_id: string, reason: string, occurred_at: string}
     */
    public function broadcastWith(): array
    {
        return [
            'schema' => 'x-change.funding-projection-changed.v1',
            'event_id' => hash('sha256', $this->receiptReference),
            'reason' => 'account_funding_settled',
            'occurred_at' => $this->occurredAt,
        ];
    }
}
