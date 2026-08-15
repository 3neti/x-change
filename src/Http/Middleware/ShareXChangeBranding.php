<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use LBHurtado\XChange\Contracts\CommercialOperatorAuthorityContract;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;
use LBHurtado\XChange\Enums\ProvisioningOperatorCapability;
use LBHurtado\XChange\Enums\TreasuryOperatorCapability;
use LBHurtado\XChange\Services\Cockpit\CockpitSystemReadinessAccess;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiOperatorAuthority;
use LBHurtado\XChange\Services\Provisioning\ProvisioningOperatorAuthority;
use LBHurtado\XChange\Services\Treasury\TreasuryOperatorAuthority;
use Symfony\Component\HttpFoundation\Response;

class ShareXChangeBranding
{
    public function __construct(
        private readonly CockpitSystemReadinessAccess $systemReadinessAccess,
        private readonly CommercialOperatorAuthorityContract $commercialAuthority,
        private readonly PartnerApiOperatorAuthority $partnerApiAuthority,
        private readonly TreasuryOperatorAuthority $treasuryAuthority,
        private readonly ProvisioningOperatorAuthority $provisioningAuthority,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share('xchange', [
            ...(array) Inertia::getShared('xchange', []),
            'branding' => [
                'name' => (string) config('x-change.branding.name', config('x-change.product.name', 'X-Change')),
                'logo_light' => (string) config('x-change.branding.logo_light', '/vendor/x-change/images/brand-library/x-change/svg/x-change-logo.svg'),
                'logo_dark' => (string) config('x-change.branding.logo_dark', '/vendor/x-change/images/brand-library/x-change/svg/x-change-light.svg'),
            ],
            'navigation' => [
                'system_readiness_visible' => $this->systemReadinessAccess->isVisible(),
                'commercial_controls_visible' => $request->user() instanceof Model
                    && $this->mayViewCommercialControls($request->user()),
                'api_partner_controls_visible' => $request->user() instanceof Model
                    && $this->partnerApiAuthority->mayView($request->user()),
                'treasury_operations_visible' => $request->user() instanceof Model
                    && ($this->treasuryAuthority->allows(
                        $request->user(), TreasuryOperatorCapability::ViewAccountGrants,
                    ) || $this->treasuryAuthority->allows(
                        $request->user(), TreasuryOperatorCapability::ViewInstitutionFunds,
                    ) || $this->treasuryAuthority->allows(
                        $request->user(), TreasuryOperatorCapability::ViewReconciliation,
                    )),
                'provisioning_controls_visible' => $request->user() instanceof Model
                    && $this->provisioningAuthority->allows(
                        $request->user(), ProvisioningOperatorCapability::View,
                    ),
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
