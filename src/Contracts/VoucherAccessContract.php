<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;

interface VoucherAccessContract
{
    public function findByCode(string $code): ?Voucher;

    public function findByCodeOrFail(string $code): Voucher;

    public function assertRedeemable(Voucher $voucher): void;
}
