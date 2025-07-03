<?php

namespace App\Http\Middleware\Redeem;

use Illuminate\Support\Facades\{Log, Session};
use Symfony\Component\HttpFoundation\Response;
use Propaganistas\LaravelPhone\Rules\Phone;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Closure;

class CheckMobileMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $voucher = $request->route('voucher');
        $voucherCode = $voucher->code;
        $mobile = Session::get("redeem.{$voucherCode}.mobile");

        Log::info('[CheckMobileMiddleware] Validating session mobile', compact('voucherCode', 'mobile'));

        $validator = Validator::make(
            ['mobile' => $mobile],
            ['mobile' => ['required', (new Phone)->country('PH')->type('mobile')]]
        );

        if ($validator->fails()) {
            Log::warning('[CheckMobileMiddleware] Validation failed', [
                'voucher' => $voucherCode,
                'errors' => $validator->errors()->all(),
            ]);
            abort(Response::HTTP_BAD_REQUEST, 'Invalid Philippine mobile number');
        }

        return $next($request);
    }
}
