<?php

namespace App\Http\Middleware\Redeem;

use App\Actions\VerifyMobile;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;
use Closure;

class SendOTPMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $voucher = $request->route('voucher');
        $voucherCode = $voucher->code;
        $mobile = Session::get("redeem.{$voucherCode}.mobile");

        Log::info('[SendOTPMiddleware] Preparing to send OTP', [
            'voucher_code' => $voucherCode,
            'mobile' => $mobile,
            'session_key' => "redeem.{$voucherCode}.mobile",
        ]);

        $response = $next($request);

        $uri = VerifyMobile::run($mobile);

        Session::put("redeem.{$voucherCode}.uri", $uri);

        Log::info('[SendOTPMiddleware] OTP verification URI stored in session', [
            'voucher_code' => $voucherCode,
            'uri' => $uri,
            'session_key' => "redeem.{$voucherCode}.uri",
        ]);

        return $response;
    }
}
