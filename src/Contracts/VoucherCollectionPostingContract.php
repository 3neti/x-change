<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Payment\ConfirmedVoucherCollectionData;
use LBHurtado\XChange\Data\Payment\VoucherCollectionPostingData;

interface VoucherCollectionPostingContract
{
    public function driver(): string;

    public function post(
        Voucher $voucher,
        ConfirmedVoucherCollectionData $collection,
    ): VoucherCollectionPostingData;
}
