<?php

namespace LBHurtado\PaymentGateway\Pipelines\ResolvePayable;

use LBHurtado\PaymentGateway\Data\Netbank\Deposit\Helpers\RecipientAccountNumberData;
use Bavix\Wallet\Models\Wallet;
use Closure;

class CheckMobile
{
    public function handle(RecipientAccountNumberData $recipientAccountNumberData, Closure $next)
    {
        $user = app(config('payment-gateway.models.user'))::findByMobile($recipientAccountNumberData->referenceCode);

        if ($user?->wallet instanceof Wallet)
            return $user;

        return $next($recipientAccountNumberData);
    }
}
