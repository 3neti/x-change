<?php

namespace App\Http\Middleware\Redeem;

use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use App\Validators\VoucherRedemptionValidator;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;
use Closure;

/**
 * EnsurePayeeSecretMiddleware validates that the secret provided by
 * the redeemer matches the hashed secret associated with the voucher.
 *
 * This provides server-side protection to ensure the redeemer is authorized
 * to access or use the voucher, even if the frontend already performed
 * client-side validation.
 *
 * If the voucher has no associated secret, the check is skipped.
 *
 * Expected session key: "redeem.{voucher_code}.secret"
 */
class EnsurePayeeSecretMiddleware
{
    /**
     * Handle an incoming request and validate the payee's secret (if required).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): \Symfony\Component\HttpFoundation\Response  $next
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $voucher = $request->route('voucher');
        $voucherCode = $voucher->code ?? null;
        $secret = Session::get("redeem.{$voucherCode}.secret");

        $validator = new VoucherRedemptionValidator($voucher);

        if (! $validator->validateSecret($secret)) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $next($request);
    }
}
