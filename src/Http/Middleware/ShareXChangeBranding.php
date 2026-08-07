<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use LBHurtado\XChange\Contracts\CommercialOperatorAuthorityContract;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Services\Cockpit\CockpitSystemReadinessAccess;
use Symfony\Component\HttpFoundation\Response;

class ShareXChangeBranding
{
    public function __construct(
        private readonly CockpitSystemReadinessAccess $systemReadinessAccess,
        private readonly CommercialOperatorAuthorityContract $commercialAuthority,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share('xchange', [
            'branding' => [
                'name' => (string) config('x-change.branding.name', config('x-change.product.name', 'X-Change')),
                'logo_light' => (string) config('x-change.branding.logo_light', '/vendor/x-change/images/logo-orange.png'),
                'logo_dark' => (string) config('x-change.branding.logo_dark', '/vendor/x-change/images/logo-silver.png'),
            ],
            'navigation' => [
                'system_readiness_visible' => $this->systemReadinessAccess->isVisible(),
                'commercial_controls_visible' => $request->user() instanceof Model
                    && $this->mayViewCommercialControls($request->user()),
            ],
        ]);

        return $next($request);
    }

    private function mayViewCommercialControls(Model $operator): bool
    {
        foreach (CommercialOperatorCapability::cases() as $capability) {
            if ($this->commercialAuthority->allows($operator, $capability)) {
                return true;
            }
        }

        return false;
    }
}
