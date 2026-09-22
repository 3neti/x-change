<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use LBHurtado\XChange\Data\Payment\PaymentObservationNoticeData;
use LBHurtado\XChange\Services\Funding\FundingProjectionChannel;

final class PaymentTransactionObserved implements ShouldBroadcastNow, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable;

    public function __construct(
        private readonly string $ownerType,
        private readonly string $ownerId,
        public readonly PaymentObservationNoticeData $notice,
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
        return 'PaymentTransactionObserved';
    }

    /** @return array{schema: string, event_id: string, reason: string, occurred_at: string} */
    public function broadcastWith(): array
    {
        return [
            'schema' => 'x-change.payment-transaction-observed.v1',
            'event_id' => hash_hmac('sha256', (string) $this->notice->statusId, (string) config('app.key')),
            'reason' => $this->notice->reason,
            'occurred_at' => $this->notice->occurredAt,
        ];
    }
}
