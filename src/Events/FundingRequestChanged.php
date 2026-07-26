<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LBHurtado\XChange\Services\Funding\FundingProjectionChannel;

final class FundingRequestChanged implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        private readonly string $recipientType,
        private readonly string $recipientId,
        private readonly string $requestReference,
        private readonly string $status,
        private readonly int $version,
        private readonly string $occurredAt,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(
            app(FundingProjectionChannel::class)->nameForIdentity(
                $this->recipientType,
                $this->recipientId,
            ),
        );
    }

    public function broadcastAs(): string
    {
        return 'FundingRequestChanged';
    }

    /**
     * @return array{
     *     schema: string,
     *     event_id: string,
     *     reason: string,
     *     request_reference: string,
     *     status: string,
     *     occurred_at: string
     * }
     */
    public function broadcastWith(): array
    {
        return [
            'schema' => 'x-change.funding-request-changed.v1',
            'event_id' => hash('sha256', implode("\0", [
                $this->requestReference,
                $this->status,
                (string) $this->version,
            ])),
            'reason' => 'funding_request_changed',
            'request_reference' => $this->requestReference,
            'status' => $this->status,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
