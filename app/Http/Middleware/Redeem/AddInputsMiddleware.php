<?php

namespace App\Http\Middleware\Redeem;

use Illuminate\Support\Facades\{Log, Session};
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;
use Closure;

class AddInputsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $voucher = $request->route('voucher');
        $voucherCode = $voucher->code;

        Log::info('[AddInputsMiddleware] Validating inputs for', compact('voucherCode'));

        $inputs = Session::get("redeem.{$voucherCode}.inputs");

        if (empty($inputs)) {
            Log::warning('[AddInputsMiddleware] No inputs found in session', compact('voucherCode'));
            abort(Response::HTTP_BAD_REQUEST, 'Missing user inputs');
        }

        return $next($request);
    }
}
