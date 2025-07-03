<?php

namespace App\Http\Middleware\Redeem;

use Symfony\Component\HttpFoundation\Response;
use Propaganistas\LaravelPhone\PhoneNumber;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Actions\EncashCheck;
use Closure;

/**
 * RedeemVoucherMiddleware is the final middleware in the redemption flow.
 *
 * It attempts to redeem (encash) the voucher using session-scoped user data.
 * If successful, it allows navigation to the success page.
 * Any error during redemption is logged but does not block final rendering.
 */
class RedeemVoucherMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $voucher = $request->route('voucher');

        if (! $voucher) {
            Log::error('[RedeemVoucherMiddleware] No voucher found in route.');
            abort(Response::HTTP_BAD_REQUEST, 'Invalid voucher route binding.');
        }

        $voucherCode = $voucher->code;
        Log::info('[RedeemVoucherMiddleware] Attempting voucher redemption', [
            'voucher_code' => $voucherCode,
        ]);

        // Retrieve phone number from session and construct PhoneNumber instance
        $mobile  = Session::get("redeem.{$voucherCode}.mobile");
        $country = Session::get("redeem.{$voucherCode}.country", config('x-change.redeem.default_country', 'PH'));

        if (! $mobile) {
            Log::warning('[RedeemVoucherMiddleware] No mobile number found in session.', [
                'voucher_code' => $voucherCode,
            ]);
            abort(Response::HTTP_BAD_REQUEST, 'Missing mobile number for redemption.');
        }

        $phoneNumber = new PhoneNumber(number: $mobile, country: $country);

        // Assemble metadata for encashment
        $meta = [];

        $inputs = Session::get("redeem.{$voucherCode}.inputs", []);
        $meta['inputs'] = $inputs;

        $bankCode      = Session::get("redeem.{$voucherCode}.bank_code");
        $accountNumber = Session::get("redeem.{$voucherCode}.account_number");

        if (! empty($bankCode) && ! empty($accountNumber)) {
            $meta['bank_account'] = "{$bankCode}:{$accountNumber}";
        }

        $signature = Session::get("redeem.{$voucherCode}.signature");
        $meta['signature'] = $signature;

        Log::debug('[RedeemVoucherMiddleware] Redemption payload prepared', [
            'voucher_code' => $voucherCode,
            'mobile'       => $phoneNumber->formatForMobileDialingInCountry($country),
            'meta'         => $meta,
        ]);

        try {
            $success = EncashCheck::run($voucher, $phoneNumber, $meta);
            Log::info('[RedeemVoucherMiddleware] EncashCheck result', [
                'voucher_code' => $voucherCode,
                'success'      => $success,
            ]);
        } catch (\Throwable $e) {
            Log::error('[RedeemVoucherMiddleware] EncashCheck failed', [
                'voucher_code' => $voucherCode,
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
            ]);
            $success = false;
        }

        if ($success) {
            Session::put("redeem.{$voucherCode}.redeemed", true);
        }

        return $next($request);
    }
}
