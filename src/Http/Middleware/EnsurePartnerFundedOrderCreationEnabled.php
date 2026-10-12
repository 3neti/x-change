<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePartnerFundedOrderCreationEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('x-change.partner_api.funded_orders.enabled', false), 404);
        abort_unless((bool) config('x-change.public_auto_generate.enabled', false), 503);
        abort_unless((bool) config('x-change.issuance_funding.on_demand.enabled', false), 503);

        return $next($request);
    }
}
