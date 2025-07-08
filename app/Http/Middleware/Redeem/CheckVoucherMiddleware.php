<?php

namespace App\Http\Middleware\Redeem;

use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use LBHurtado\Voucher\Models\Voucher;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Closure;

/**
 * CheckVoucherMiddleware ensures the voucher in the route is valid and redeemable.
 *
 * If valid, it stores the voucher code in session using a scoped key (per voucher).
 * If invalid, it halts with a 400 error.
 */
class CheckVoucherMiddleware
{
    /**
     * Handle an incoming request by checking voucher validity and scoping it to session.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure(\Illuminate\Http\Request): \Symfony\Component\HttpFoundation\Response $next
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws ValidationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \LBHurtado\Voucher\Models\Voucher|null $voucher */
        $voucher = $request->route('voucher');

        // Validate that the route param is a bound Voucher instance
        if (! $voucher instanceof Voucher) {
            Log::warning('[CheckVoucherMiddleware] Voucher not found in route.');

            throw ValidationException::withMessages([
                'voucher_code' => 'Cash code not found.',
            ]);
        }

        // Check redeemability using package's built-in helper
        if (! Vouchers::redeemable($voucher->code)) {
            Log::warning('[CheckVoucherMiddleware] Voucher not redeemable', [
                'code'        => $voucher->code,
                'is_redeemed' => $voucher->isRedeemed(),
                'is_started'  => $voucher->isStarted(),
                'is_expired'  => $voucher->isExpired(),
            ]);

            $message = match (true) {
                $voucher->isRedeemed() => 'This cash code has already been redeemed.',
                ! $voucher->isStarted() => 'This cash code is not yet active.',
                $voucher->isExpired() => 'This cash code has expired.',
                default => 'This cash code is not redeemable.',
            };

            throw ValidationException::withMessages([
                'voucher_code' => $message,
            ]);
        }

        return $next($request);
    }
}
