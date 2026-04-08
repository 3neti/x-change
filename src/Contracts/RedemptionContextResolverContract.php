<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Data\RedemptionContext;

interface RedemptionContextResolverContract
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function resolve(array $payload): RedemptionContext;
}
