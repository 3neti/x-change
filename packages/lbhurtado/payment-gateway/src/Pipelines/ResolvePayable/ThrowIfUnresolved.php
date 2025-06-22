<?php

namespace LBHurtado\PaymentGateway\Pipelines\ResolvePayable;

use LBHurtado\PaymentGateway\Data\Netbank\Deposit\Helpers\RecipientAccountNumberData;
use Closure;

class ThrowIfUnresolved
{
    public function handle(RecipientAccountNumberData $recipientAccountNumberData, Closure $next)
    {
        throw new \RuntimeException("Could not resolve {$recipientAccountNumberData->referenceCode}");
    }
}
