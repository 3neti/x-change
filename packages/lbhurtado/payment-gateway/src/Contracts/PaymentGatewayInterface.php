<?php

namespace LBHurtado\PaymentGateway\Contracts;

use Brick\Money\Money;

interface PaymentGatewayInterface
{
    public function generate(string $account, Money $amount): string;

    /**
     * Confirm a deposit transaction sent by the payment gateway (e.g., QR Ph).
     *
     * @param array $payload  The validated deposit webhook payload.
     * @return bool Whether the confirmation was successful.
     */
    public function confirmDeposit(array $payload): bool;
}
