<?php

namespace LBHurtado\PaymentGateway\Data\Netbank;

use Spatie\LaravelData\Data;

class GatewayResponseData extends Data
{
    public function __construct(
        public string $uuid,
        public string $transaction_id,
        public string $status,
    ) {}
}
