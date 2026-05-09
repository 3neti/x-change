<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Voucher\Models\Voucher;

class ClaimSuccessPageController extends Controller
{
    public function __invoke(string $code): Response
    {
        $code = strtoupper(trim($code));

        $voucher = Voucher::query()->where('code', $code)->firstOrFail();

        $amount = $voucher->metadata['disbursement']['amount']
            ?? $voucher->instructions->cash->amount
            ?? 0;
        $currency = $voucher->instructions->cash->currency ?? 'PHP';
        $riderTimeout = $voucher->instructions->rider->redirect_timeout ?? 10;

        return Inertia::render('x-change/claim/Success', [
            'voucher' => [
                'code' => $voucher->code,
                'amount' => $amount,
                'formatted_amount' => '₱'.number_format((float) $amount, 2),
                'currency' => $currency,
            ],
            'rider' => [
                'message' => $voucher->instructions->rider->message ?? null,
                'url' => $voucher->instructions->rider->url ?? null,
            ],
            'redirect_timeout' => $riderTimeout,
        ]);
    }
}
