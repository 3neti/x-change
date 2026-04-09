<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;

interface WithdrawalValidationContract
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function validate(Voucher $voucher, array $payload): void;
}
