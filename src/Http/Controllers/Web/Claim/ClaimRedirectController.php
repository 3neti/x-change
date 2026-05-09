<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\AuditLoggerContract;

class ClaimRedirectController extends Controller
{
    public function __invoke(string $code, AuditLoggerContract $audit): RedirectResponse
    {
        $code = strtoupper(trim($code));

        $voucher = Voucher::query()->where('code', $code)->firstOrFail();

        $riderUrl = $voucher->instructions->rider->url ?? null;

        $audit->log('pay_code.claim.redirect', [
            'voucher_code' => $code,
            'rider_url' => $riderUrl,
        ]);

        if ($riderUrl && filter_var($riderUrl, FILTER_VALIDATE_URL)) {
            return redirect()->away($riderUrl);
        }

        return redirect()->route('x-change.claim.success', ['code' => $code]);
    }
}
