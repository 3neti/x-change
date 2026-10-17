<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Contracts\Commercial\PublicIssuanceEscrowPrincipalResolverContract;
use LBHurtado\XChange\Contracts\SettlementRailCapabilityRegistryContract;
use LBHurtado\XChange\Services\Checkout\PublicCheckoutDraftAccess;
use LBHurtado\XChange\Services\Cockpit\OnDemandIssuanceFundingOrderPresenter;
use LBHurtado\XChange\Services\Configuration\InstructionCapabilityReadinessRegistry;
use LBHurtado\XChange\Services\PublicIssuance\PublicIssuanceDiscoveryService;
use LBHurtado\XChange\Services\PublicIssuance\PublicIssuanceInput;
use LBHurtado\XChange\Services\PublicIssuance\PublicIssuanceOrderAccess;
use LBHurtado\XChange\Services\PublicIssuance\PublicIssuanceTurnstileVerifier;
use LBHurtado\XChange\Support\Cockpit\CockpitReadOnlyPageProps;

final class PublicAutoGeneratePageController extends Controller
{
    public function __construct(
        private readonly CockpitReadOnlyPageProps $props,
        private readonly PublicIssuanceEscrowPrincipalResolverContract $principals,
        private readonly InstructionCapabilityReadinessRegistry $instructionCapabilities,
        private readonly SettlementRailCapabilityRegistryContract $settlementRails,
        private readonly PublicIssuanceOrderAccess $publicOrderAccess,
        private readonly OnDemandIssuanceFundingOrderPresenter $fundingOrderPresenter,
        private readonly PublicIssuanceInput $publicIssuanceInput,
        private readonly PublicIssuanceDiscoveryService $publicIssuanceDiscovery,
        private readonly PublicIssuanceTurnstileVerifier $turnstile,
        private readonly PublicCheckoutDraftAccess $checkoutDrafts,
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
        data_set($props, 'quick_generate_read_model.mutation_contract.authorization', 'public-issuance-escrow-server-bound');
        data_set($props, 'quick_generate_read_model.mutation_contract.runtime_enabled', $enabled);
        data_set($props, 'quick_generate_read_model.mutation_contract.allowed_methods', ['GET', 'POST']);

        data_set($props, 'quick_generate_read_model.claim_preview_contract.route', 'x-change.public-auto-generate.claim-previews.store');
        data_set($props, 'quick_generate_read_model.claim_preview_contract.route_url', route('x-change.public-auto-generate.claim-previews.store', [], false));
        data_set($props, 'quick_generate_read_model.claim_preview_contract.authorization', 'guest-session');

        unset($props['cockpit_header_read_model'], $props['cockpit_entry_notice']);

        $activeOrder = $this->publicOrderAccess->active($request);
        $prefill = $this->publicIssuanceInput->prefill(
            $request->query('amount'),
            $request->query('currency'),
        );
        $discovery = $this->publicIssuanceDiscovery->describe();
        $draft = $this->checkoutDrafts->active($request, $principal);
        $workspace = in_array($request->query('workspace'), ['composer', 'llm'], true)
            ? (string) $request->query('workspace')
            : 'composer';

        return Inertia::render('x-change/public/AutoGenerate', [
            ...$props,
            'display_campaigns' => [],
            'display_session' => null,
            'checkout_draft' => $draft === null ? null : [
                'reference' => $draft['checkout']->reference,
                'guest_token' => $draft['token'],
                'instructions' => $draft['checkout']->instructions_ciphertext,
            ],
            'surface_profile' => [
                'kind' => 'public_auto_generate',
                'show_funding_navigation' => false,
                'show_engineering_preview' => false,
                'show_workspace_switcher' => true,
                'allow_template_management' => false,
                'available_surfaces' => ['composer', 'llm'],
            ],
            'public_navigation' => [
                'analytics_url' => Route::has('x-change.analytics.show')
                    ? route('x-change.analytics.show', [], false)
                    : null,
                'pricing_url' => Route::has('x-change.pricing.show')
                    ? route('x-change.pricing.show', [], false)
                    : null,
                'claim_url' => Route::has('x-change.claim.start')
                    ? route('x-change.claim.start', [], false)
                    : null,
                'create_url' => route('x-change.public-auto-generate.show', [], false),
                'login_url' => Route::has('login') ? route('login', [], false) : null,
                'register_url' => Route::has('register') ? route('register', [], false) : null,
            ],
            'batch_endpoints' => [
                'preview' => route('x-change.public-auto-generate.batches.preview', [], false),
                'create' => route('x-change.public-auto-generate.batches.store', [], false),
                'fund' => route('x-change.public-auto-generate.batches.fund', ['batch' => '__REFERENCE__'], false),
                'template' => route('x-change.public-auto-generate.batches.template', [], false),
            ],
            'batch_recovery_endpoints' => [
                'request' => route('x-change.public-auto-generate.batches.recovery.discovery.request', [], false),
                'verify' => route('x-change.public-auto-generate.batches.recovery.discovery.verify', [], false),
                'select' => route('x-change.public-auto-generate.batches.recovery.discovery.select', [], false),
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
            'initial_workspace' => $workspace,
            'startup_mode' => 'blank',
            'public_prefill' => $prefill,
            'turnstile' => [
                'enabled' => $this->turnstile->enabled(),
                'site_key' => $this->turnstile->siteKey(),
            ],
            'service_discovery' => [
                'canonical_url' => $discovery->canonical_url,
                'description' => 'Create, fund, and share a Pay Code without opening an account.',
                'structured_data' => [
                    '@context' => 'https://schema.org',
                    '@type' => 'Service',
                    'name' => 'On-Demand Pay Code Issuance',
                    'description' => 'Create, fund, and share a Pay Code without opening an account.',
                    'url' => $discovery->canonical_url,
                    'provider' => [
                        '@type' => 'Organization',
                        'name' => (string) config('app.name', 'x-change'),
                    ],
                ],
            ],
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
