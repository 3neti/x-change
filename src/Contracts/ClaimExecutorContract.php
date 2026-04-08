<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;

interface ClaimExecutorContract
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Voucher $voucher, array $payload): mixed;
}
