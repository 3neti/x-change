<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ShareXChangeBranding
{
    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share('xchange', [
            'branding' => [
                'name' => (string) config('x-change.branding.name', config('x-change.product.name', 'X-Change')),
                'logo_light' => (string) config('x-change.branding.logo_light', '/vendor/x-change/images/logo-orange.png'),
                'logo_dark' => (string) config('x-change.branding.logo_dark', '/vendor/x-change/images/logo-silver.png'),
            ],
        ]);

        return $next($request);
    }
}
