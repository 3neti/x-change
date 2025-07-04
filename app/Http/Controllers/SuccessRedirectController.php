<?php

namespace App\Http\Controllers;

use LBHurtado\Voucher\Models\Voucher;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

/**
 * Handles the post-redemption redirect based on voucher instructions.
 *
 * Redirects the user to the URL specified in the voucher's rider instructions,
 * if present. Otherwise, falls back to a default route.
 */
class SuccessRedirectController extends Controller
{
    /**
     * Handle the incoming request and redirect accordingly.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \LBHurtado\Voucher\Models\Voucher  $voucher
     * @return \Illuminate\Http\RedirectResponse|\Inertia\Response|\Symfony\Component\HttpFoundation\Response
     */
    public function __invoke(Request $request, Voucher $voucher)
    {
        Log::info("[SuccessRedirectController] Invoked for voucher: {$voucher->code}");

        $redirectUrl = $voucher->instructions->rider->url ?? null;

        if ($redirectUrl) {
            Log::info("[SuccessRedirectController] Redirecting to rider URL: {$redirectUrl}");
            return inertia()->location($redirectUrl);
        }

        $fallbackUrl = config('x-change.redeem.success.rider');
        Log::warning("[SuccessRedirectController] No rider URL found; redirecting to fallback: {$fallbackUrl}");

        return inertia()->location($fallbackUrl);
    }
}
