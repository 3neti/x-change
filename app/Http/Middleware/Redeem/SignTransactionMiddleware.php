<?php

namespace App\Http\Middleware\Redeem;

use Illuminate\Support\Facades\{Log, Session};
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;
use Closure;

class SignTransactionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $voucher = $request->route('voucher');
        $voucherCode = $voucher->code;
        $signature = Session::get("redeem.{$voucherCode}.signature");

        Log::info('[SignTransactionMiddleware] Checking signature for', [
            'voucher' => $voucherCode,
            'has_signature' => !empty($signature),
        ]);

        if (empty($signature)) {
            abort(Response::HTTP_BAD_REQUEST, 'Missing signature');
        }

        return $next($request);
    }
}
