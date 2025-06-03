<?php

namespace LBHurtado\PaymentGateway\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\Channel;
use Bavix\Wallet\Models\Transaction;
use Brick\Money\Money;

class DepositConfirmed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    protected string $currency = 'PHP';

    public Transaction $transaction;

    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        logger('DepositConfirmed::broadcastOn');
        logger('$this->transaction->payable->id = ' . $this->transaction->payable->id);
        return [
            new PrivateChannel('App.Models.User.' . $this->transaction->payable->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'deposit.confirmed';
    }

    public function broadcastWith(): array
    {
        $uuid = $this->transaction->uuid;
        $amount = Money::ofMinor($this->transaction->amount, $this->currency)->getAmount()->toInt();

        return [
            'uuid' => $uuid,
            'amount' => $amount,
        ];
    }
}
