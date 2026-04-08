<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;

interface ClaimExecutionFactoryContract
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function make(Voucher $voucher, array $payload): ClaimExecutorContract;
}
