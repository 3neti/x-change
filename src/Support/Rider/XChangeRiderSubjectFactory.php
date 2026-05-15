<?php

namespace LBHurtado\XChange\Support\Rider;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XRider\Data\RiderSubjectData;

class XChangeRiderSubjectFactory
{
    public function fromVoucher(Voucher $voucher): RiderSubjectData
    {
        return new RiderSubjectData(
            type: 'voucher',
            id: $voucher->getKey(),
            code: (string) $voucher->code,
            meta: [
                'voucher_id' => $voucher->getKey(),
                'voucher_code' => (string) $voucher->code,
                'amount' => data_get($voucher, 'cash.amount'),
                'currency' => data_get($voucher, 'cash.currency'),
            ],
        );
    }
}
