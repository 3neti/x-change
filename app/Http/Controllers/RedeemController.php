<?php

namespace App\Http\Controllers;

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
        Session::forget(['voucher_checked', 'mobile_checked', 'signature_checked', 'voucher_redeemed']);



    }
}
