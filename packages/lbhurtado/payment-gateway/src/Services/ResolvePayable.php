<?php

namespace LBHurtado\PaymentGateway\Services;

use LBHurtado\PaymentGateway\Pipelines\ResolvePayable\{CheckMobile, CheckVoucher, ThrowIfUnresolved};
use LBHurtado\PaymentGateway\Data\Netbank\Deposit\Helpers\RecipientAccountNumberData;
use Bavix\Wallet\Interfaces\Wallet;
use Illuminate\Pipeline\Pipeline;

class ResolvePayable
{
    public function execute(RecipientAccountNumberData $recipientAccountNumberData): Wallet
    {
        return app(Pipeline::class)
            ->send($recipientAccountNumberData)
            ->through([
                CheckMobile::class,
                CheckVoucher::class,
                ThrowIfUnresolved::class,
            ])->thenReturn();
    }
}
