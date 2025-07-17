<?php

namespace App\Http\Middleware\Redeem;

use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use App\Validators\VoucherRedemptionValidator;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;
use Closure;

/**
 * EnsurePayeeMobileMiddleware validates that the mobile number used
 * to redeem the voucher matches the validation mobile number configured
 * on the voucher (if any).
 *
 * This adds a server-side verification layer to confirm that only the
 * intended recipient is redeeming the voucher. If no validation mobile
 * is configured, the check is skipped.
 *
 * Expected session key: "redeem.{voucher_code}.mobile"
 */
class EnsurePayeeMobileMiddleware
{
    /**
     * Handle an incoming request and validate the redeemer's mobile number.
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
        $mobile = Session::get("redeem.{$voucherCode}.mobile");

        $validator = new VoucherRedemptionValidator($voucher);

        if (! $validator->validateMobile($mobile)) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $next($request);
    }
}
