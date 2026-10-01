<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Contracts\CommercialPrincipalResolverContract;
use LBHurtado\XChange\Contracts\SettlementRailCapabilityRegistryContract;
use LBHurtado\XChange\Services\Cockpit\OnDemandIssuanceFundingOrderPresenter;
use LBHurtado\XChange\Services\Configuration\InstructionCapabilityReadinessRegistry;
use LBHurtado\XChange\Services\PublicIssuance\PublicIssuanceOrderAccess;
use LBHurtado\XChange\Support\Cockpit\CockpitReadOnlyPageProps;

final class PublicAutoGeneratePageController extends Controller
{
    public function __construct(
        private readonly CockpitReadOnlyPageProps $props,
        private readonly CommercialPrincipalResolverContract $principals,
        private readonly InstructionCapabilityReadinessRegistry $instructionCapabilities,
        private readonly SettlementRailCapabilityRegistryContract $settlementRails,
        private readonly PublicIssuanceOrderAccess $publicOrderAccess,
        private readonly OnDemandIssuanceFundingOrderPresenter $fundingOrderPresenter,
    ) {}

    public function __invoke(Request $request): Response
    {
        $principal = $this->principals->resolve();
        $props = $this->props->toQuickGenerateArray();
        $enabled = (bool) config('x-change.public_auto_generate.enabled', true);

        data_set($props, 'quick_generate_read_model.authorized', true);
        data_set(
            $props,
            'quick_generate_read_model.mutation_contract.route',
            'x-change.public-auto-generate.store',
        );
        data_set(
            $props,
            'quick_generate_read_model.mutation_contract.route_url',
            $enabled ? route('x-change.public-auto-generate.store', [], false) : null,
        );
        data_set($props, 'quick_generate_read_model.mutation_contract.authorization', 'commercial-principal-server-bound');
        data_set($props, 'quick_generate_read_model.mutation_contract.runtime_enabled', $enabled);
        data_set($props, 'quick_generate_read_model.mutation_contract.allowed_methods', ['GET', 'POST']);

        unset($props['cockpit_header_read_model'], $props['cockpit_entry_notice']);

        $activeOrder = $this->publicOrderAccess->active($request);

        return Inertia::render('x-change/public/AutoGenerate', [
            ...$props,
            'display_campaigns' => [],
            'display_session' => null,
            'surface_profile' => [
                'kind' => 'public_auto_generate',
                'show_funding_navigation' => false,
                'show_engineering_preview' => false,
                'show_workspace_switcher' => false,
                'allow_template_management' => false,
            ],
            'public_navigation' => [
                'claim_url' => Route::has('x-change.claim.start')
                    ? route('x-change.claim.start', [], false)
                    : null,
                'login_url' => Route::has('login') ? route('login', [], false) : null,
                'register_url' => Route::has('register') ? route('register', [], false) : null,
            ],
            'commercial_principal' => [
                'reference' => $principal->reference,
                'display_name' => $principal->legal_name,
            ],
            'feedback_defaults' => [
                'schema' => 'x-change.cockpit.quick-generate-feedback-defaults.v1',
                'email' => null,
                'mobile' => null,
                'webhook' => null,
                'source' => 'public-visitor',
                'read_only' => true,
            ],
            'onboarding_policy' => ['otp_required' => false],
            'invitation_preset' => ['enabled' => false, 'source' => 'public'],
            'startup_mode' => 'blank',
            'last_instructions' => null,
            'saved_templates' => [],
            'rider_library' => [],
            'instruction_capabilities' => $this->instructionCapabilities->sanitized(),
            'settlement_rail_capabilities' => $this->settlementRails->sanitized(),
            'collection_destination' => null,
            'pos_voucher' => null,
            'active_on_demand_funding_order' => $activeOrder === null
                ? null
                : $this->fundingOrderPresenter->present(
                    $activeOrder['order'],
                    $activeOrder['token'],
                    true,
                ),
            'on_demand_issuance_policy' => [
                'enabled' => true,
                'basis' => 'full_amount',
            ],
        ])->rootView('x-change::claim-root');
    }
}
