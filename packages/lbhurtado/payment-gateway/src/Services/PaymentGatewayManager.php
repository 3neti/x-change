<?php

namespace LBHurtado\PaymentGateway\Services;

use LBHurtado\PaymentGateway\Gateways\Netbank\NetbankPaymentGateway;
use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Manager;

class PaymentGatewayManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return config('payment-gateway.default', 'netbank');
    }

    public function createNetbankDriver(): PaymentGatewayInterface
    {
        return new NetbankPaymentGateway();
    }

    public function createIcashDriver(): PaymentGatewayInterface
    {
        // return new ICashGateway(); // to be implemented later
        throw new \RuntimeException('iCash driver not implemented yet.');
    }
}
