<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Services\Leads\CampaignDisplaySessions;
use Symfony\Component\HttpFoundation\Response;

final readonly class GuardPairedCampaignClaim
{
    public function __construct(private CampaignDisplaySessions $displays) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('x-change.claim.*') && ! $request->routeIs('x-change.claim.complete')) {
            $code = $request->route('code') ?? $request->input('code');
            if (is_string($code) && $code !== '') {
                $voucher = Voucher::query()->where('code', strtoupper(trim($code)))->first();
                if ($voucher !== null) {
                    $this->displays->forPayer($voucher, $request);
                }
            }
        }

        return $next($request);
    }
}
