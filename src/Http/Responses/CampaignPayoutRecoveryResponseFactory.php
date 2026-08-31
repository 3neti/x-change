<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Responses;

use Illuminate\Http\Request;
use Inertia\Inertia;
use LBHurtado\XChange\Models\CampaignPayoutRecoveryGrant;
use Symfony\Component\HttpFoundation\Response;

final readonly class CampaignPayoutRecoveryResponseFactory
{
    public function render(Request $request, CampaignPayoutRecoveryGrant $grant): Response
    {
        $status = $grant->expires_at?->isPast() && ! in_array(
            $grant->status,
            ['consumed', 'submitting'],
            true,
        ) ? 'expired' : (string) $grant->status;

        $response = Inertia::render('x-change/claim/PayoutRecovery', [
            'code' => (string) $grant->voucher?->code,
            'status' => $status,
            'amount' => [
                'minor' => (int) round(((float) $grant->rejection?->amount) * 100),
                'currency' => (string) ($grant->rejection?->currency ?? 'PHP'),
            ],
            'settlement_rail' => (string) ($grant->rejection?->settlement_rail ?? 'INSTAPAY'),
            'expires_at' => $grant->expires_at?->toIso8601String(),
            'otp_expires_at' => $grant->otp_expires_at?->toIso8601String(),
            'notice' => $request->session()->get('status'),
        ])->rootView('x-change::claim-root')->toResponse($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
