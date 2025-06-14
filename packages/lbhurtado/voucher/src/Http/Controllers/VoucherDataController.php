<?php

namespace LBHurtado\Voucher\Http\Controllers;

use LBHurtado\Voucher\Models\Voucher;
use Illuminate\Http\Request;

class VoucherDataController
{
    public function __invoke(Request $request, string $code)
    {
        $voucher = Voucher::where('code', $code)->firstOrFail();

        return $voucher->getData();
    }
}
