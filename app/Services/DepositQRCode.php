<?php

namespace App\Services;

use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use Brick\Money\Money;
use App\Models\User;

class DepositQRCode
{
    public function __construct(
        protected PaymentGatewayInterface $gateway
    ) {}

    public function generate(User $user, Money $amount): string
    {
        $account = $user->mobile;

        $amountKey = $amount->getAmount();
        $currency = $amount->getCurrency()->getCurrencyCode();
        $cacheKey = "deposit_qr_{$amountKey}_{$currency}_{$account}";

        return cache()->remember($cacheKey, now()->addMinutes(30), function () use ($amount, $account) {
            logger()->info('Generating new QR code for deposit', compact('amount', 'account'));
            return $this->gateway->generate($account, $amount);
        });
    }
}
