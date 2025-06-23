<?php

namespace App\Http\Controllers;

use LBHurtado\Voucher\Data\VoucherData;
use Illuminate\Support\Facades\Session;
use LBHurtado\Voucher\Models\Voucher;
use Illuminate\Http\Request;

class RedeemController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Voucher $voucher)
    {
//        return $voucher->contact;
//
//        return $voucher->redeemers->first()->redeemer;
        Session::forget(['voucher_checked', 'mobile_checked', 'signature_checked', 'voucher_redeemed']);

        $data = VoucherData::fromModel($voucher);
return $data;
        return inertia('Redeem/Success', [
            'voucher' => $data,
            'redirectTimeout' => config('x-change.redeem.success.redirect_timeout')
        ]);
    }
}
