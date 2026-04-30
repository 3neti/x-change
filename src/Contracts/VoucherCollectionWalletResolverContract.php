<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use Bavix\Wallet\Interfaces\Wallet;
use LBHurtado\Voucher\Models\Voucher;

interface VoucherCollectionWalletResolverContract
{
    public function resolve(Voucher $voucher): Wallet;
}
