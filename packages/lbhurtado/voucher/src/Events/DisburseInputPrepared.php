<?php

namespace LBHurtado\Voucher\Events;

use LBHurtado\PaymentGateway\Data\Netbank\Disburse\DisburseInputData;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LBHurtado\Voucher\Models\Voucher;

class DisburseInputPrepared
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Voucher              $voucher,
        public DisburseInputData    $input,
    ) {}
}
