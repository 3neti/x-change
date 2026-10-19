<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use Bavix\Wallet\Interfaces\ProductInterface;

interface CommercialProductResolverContract
{
    public function resolve(string $recipientReference): ProductInterface;
}
