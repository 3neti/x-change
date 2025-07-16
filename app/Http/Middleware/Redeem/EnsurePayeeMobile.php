<?php

namespace App\Http\Middleware\Redeem;

use Symfony\Component\HttpFoundation\Response;
use Propaganistas\LaravelPhone\PhoneNumber;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Throwable;
use Closure;

/**
 * EnsurePayeeMobile validates that the voucher's validation mobile number
 * (if provided) matches the mobile number used by the redeemer.
 *
 * If no validation mobile is set in the voucher, the middleware allows
 * the request to proceed without enforcing this check.
 */
class EnsurePayeeMobile
{
    /**
     * Handle an incoming request to validate payee mobile (if required).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $voucher = $request->route('voucher');
        $voucherCode = $voucher->code ?? null;

        $validationMobileRaw = $voucher->instructions->cash->validation->mobile ?? null;

        // Only compare if voucher has a validation mobile configured
        if (!empty($validationMobileRaw)) {
            $redeemerMobileRaw = Session::get("redeem.{$voucherCode}.mobile");

            Log::info('[EnsurePayeeMobile] Validating mobile for voucher', [
                'voucher_code'    => $voucherCode,
                'expected_mobile' => $validationMobileRaw,
                'actual_mobile'   => $redeemerMobileRaw,
            ]);

            try {
                $expected = new PhoneNumber($validationMobileRaw, 'PH');
                $actual   = new PhoneNumber($redeemerMobileRaw, 'PH');

                if (! $expected->equals($actual)) {
                    Log::warning('[EnsurePayeeMobile] Mobile number mismatch.', [
                        'expected' => (string) $expected,
                        'actual'   => (string) $actual,
                    ]);
                    abort(Response::HTTP_BAD_REQUEST, 'Invalid recipient mobile number');
                }

                Log::info('[EnsurePayeeMobile] Mobile number match confirmed.', [
                    'voucher_code' => $voucherCode,
                ]);
            } catch (Throwable $e) {
                Log::error('[EnsurePayeeMobile] Error validating mobile numbers.', [
                    'voucher_code' => $voucherCode,
                    'error'        => $e->getMessage(),
                ]);
                abort(Response::HTTP_BAD_REQUEST, 'Error validating recipient mobile number');
            }
        } else {
            Log::info('[EnsurePayeeMobile] No validation mobile set on voucher; skipping match.', [
                'voucher_code' => $voucherCode,
            ]);
        }

        return $next($request);
    }
}
