<?php

namespace LBHurtado\PaymentGateway\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class DisbursementConfirmed extends DisbursementEvent implements ShouldBroadcast
{
    public function broadcastAs(): string
    {
        return 'disbursement.confirmed';
    }
}
