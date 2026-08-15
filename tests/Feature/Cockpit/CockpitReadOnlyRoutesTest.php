<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityRecordData;
use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\PayCode\GeneratePayCodeResultData;
use LBHurtado\XChange\Data\PayCodeLinksData;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Http\Controllers\PayCode\GeneratePayCodeController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitFundingIntentController;
use LBHurtado\XChange\Http\Controllers\Web\Cockpit\CockpitQrPhFundingSimulationController;
use LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository;

it('registers read-only cockpit routes under the x cockpit namespace', function () {
    expect(route('x-change.cockpit.dashboard'))->toBe('http://localhost/x/cockpit')
        ->and(route('x-change.cockpit.funding.index'))->toBe('http://localhost/x/cockpit/funding')
        ->and(route('x-change.cockpit.quick-generate'))->toBe('http://localhost/x/cockpit/quick-generate')
        ->and(route('x-change.cockpit.documentation'))->toBe('http://localhost/x/cockpit/documentation')
        ->and(route('x-change.cockpit.diagnostics.runtime-profile'))->toBe('http://localhost/x/cockpit/diagnostics/runtime-profile')
        ->and(route('x-change.cockpit.pay-codes.index'))->toBe('http://localhost/x/cockpit/pay-codes')
        ->and(route('x-change.cockpit.pay-codes.show', ['code' => 'PC-READY-001']))->toBe('http://localhost/x/cockpit/pay-codes/PC-READY-001')
        ->and(route('x-change.cockpit.pay-codes.payout-corrections.store', ['code' => 'PC-READY-001']))->toBe('http://localhost/x/cockpit/pay-codes/PC-READY-001/payout-corrections')
        ->and(route('x-change.cockpit.pay-codes.terminal-actions.store', ['code' => 'PC-READY-001']))->toBe('http://localhost/x/cockpit/pay-codes/PC-READY-001/terminal-actions')
        ->and(route('x-change.cockpit.pay-codes.distribution', ['code' => 'PC-READY-001']))->toBe('http://localhost/x/cockpit/pay-codes/PC-READY-001/distribution');
});

it('requires authentication before accepting a Pay Code payout correction', function (): void {
    $this->postJson(route(
        'x-change.cockpit.pay-codes.payout-corrections.store',
        ['code' => 'PC-READY-001'],
    ), [
        'bank_code' => 'GXCHPHM2XXX',
        'account_number' => '09173011987',
    ])->assertUnauthorized();
});

it('renders the cockpit documentation hub without exposing operational secrets', function (): void {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.documentation'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Documentation')
        ->assertJsonPath('props.documentation.schema', 'x-change.cockpit.documentation.v1')
        ->assertJsonCount(3, 'props.documentation.sections')
        ->assertJsonPath('props.documentation.sections.0.title', 'Use x-change')
        ->assertJsonPath('props.documentation.sections.1.title', 'Operate x-change')
        ->assertJsonPath('props.documentation.sections.2.title', 'Build with x-change')
        ->assertJsonPath('props.xchange.navigation.system_readiness_visible', false)
        ->assertJsonMissing(['label' => 'System Readiness'])
        ->assertJsonMissingPath('props.documentation.credentials')
        ->assertJsonMissingPath('props.documentation.secrets');
});

it('includes system readiness navigation when the authenticated workspace is enabled', function (): void {
    config()->set('x-change.cockpit.system_readiness.visible', true);
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.documentation'))
        ->assertOk()
        ->assertJsonPath('props.xchange.navigation.system_readiness_visible', true)
        ->assertJsonPath('props.documentation.sections.1.links.1.label', 'System Readiness');
});

it('renders cockpit pages as read-only inertia endpoints', function (string $route, array $parameters, string $component) {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route($route, $parameters))
        ->assertOk()
        ->assertJsonPath('component', $component)
        ->assertJsonPath('props.can.view_cockpit', true)
        ->assertJsonPath('props.can.mutate_vouchers', false)
        ->assertJsonPath('props.can.execute_drivers', false)
        ->assertJsonPath('props.can.write_journal_entries', false)
        ->assertJsonPath('props.can.send_feedback', false)
        ->assertJsonPath('props.can.call_providers', false)
        ->assertJsonPath('props.can.move_money', false)
        ->assertJsonPath('props.redaction.policy', 'default-cockpit-redaction')
        ->assertJsonPath('props.redaction.payloads', 'redacted-until-authorized-read-models-exist')
        ->assertJsonPath('props.cockpit_header_read_model.schema', 'x-change.cockpit.header-read-model.v2')
        ->assertJsonPath('props.cockpit_header_read_model.authorized', true)
        ->assertJsonPath('props.cockpit_header_read_model.read_only', true)
        ->assertJsonPath('props.cockpit_header_read_model.balances.0.key', 'internal')
        ->assertJsonPath('props.cockpit_header_read_model.balances.1.key', 'outstanding')
        ->assertJsonPath('props.cockpit_header_read_model.balances.2.key', 'issuance')
        ->assertJsonCount(3, 'props.cockpit_header_read_model.balances')
        ->assertJsonPath('props.cockpit_header_read_model.redactions.provider_balance_exposed', false)
        ->assertJsonPath('props.cockpit_header_read_model.redactions.calls_providers', false);
})->with([
    'dashboard' => ['x-change.cockpit.dashboard', [], 'x-change/cockpit/Dashboard'],
    'funding' => ['x-change.cockpit.funding.index', [], 'x-change/cockpit/Funding'],
    'quick generate' => ['x-change.cockpit.quick-generate', [], 'x-change/cockpit/QuickGenerate'],
    'pay code explorer' => ['x-change.cockpit.pay-codes.index', [], 'x-change/cockpit/PayCodeExplorer'],
    'voucher detail' => ['x-change.cockpit.pay-codes.show', ['code' => 'PC-READY-001'], 'x-change/cockpit/VoucherDetail'],
    'distribution workspace' => ['x-change.cockpit.pay-codes.distribution', ['code' => 'PC-READY-001'], 'x-change/cockpit/DistributionWorkspace'],
]);

it('hydrates funding operations with secure read-only controls', function () {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.funding.index'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Funding')
        ->assertJsonPath('props.funding_read_model.schema', 'x-change.cockpit.funding-read-model.v1')
        ->assertJsonPath('props.funding_read_model.authorized', true)
        ->assertJsonPath('props.funding_read_model.read_only', true)
        ->assertJsonPath('props.funding_read_model.summary.awaiting_funds', 0)
        ->assertJsonPath('props.funding_read_model.summary.open_suspense', 0)
        ->assertJsonPath('props.funding_read_model.controls.funding_intent_required', true)
        ->assertJsonPath('props.funding_read_model.controls.manual_balance_adjustment_enabled', false)
        ->assertJsonPath('props.funding_read_model.controls.webhook_direct_credit_enabled', false)
        ->assertJsonPath('props.funding_read_model.controls.authoritative_provider_verification_required', true)
        ->assertJsonPath('props.funding_read_model.controls.dual_control_reconciliation_required', true)
        ->assertJsonPath('props.funding_read_model.controls.can_view_treasury_controls', false)
        ->assertJsonPath('props.funding_read_model.controls.can_refresh_provider_liquidity', false)
        ->assertJsonCount(0, 'props.funding_read_model.treasury_positions')
        ->assertJsonCount(0, 'props.funding_read_model.treasury_portfolio')
        ->assertJsonPath('props.funding_read_model.redactions.payloads', 'funding-operations-summary-only')
        ->assertJsonPath('props.funding_read_model.redactions.webhook_payloads_exposed', false)
        ->assertJsonPath('props.funding_read_model.redactions.raw_evidence_exposed', false)
        ->assertJsonPath('props.funding_read_model.redactions.treasury_controls_exposed', false)
        ->assertJsonPath('props.funding_read_model.redactions.provider_liquidity_exposed', false)
        ->assertJsonPath('props.funding_read_model.redactions.provider_inventory_exposed', false)
        ->assertJsonPath('props.funding_qr_merchant_profile.name', fn (mixed $name): bool => is_string($name) && trim($name) !== '')
        ->assertJsonPath('props.funding_qr_merchant_profile.city', fn (mixed $city): bool => is_string($city) && trim($city) !== '')
        ->assertJsonPath('props.funding_qr_merchant_profile.rendered_label', fn (mixed $label): bool => is_string($label) && trim($label) !== '')
        ->assertJsonPath('props.funding_qr_merchant_profile.maximum_label_length', 25)
        ->assertJsonPath('props.funding_qr_merchant_profile.uppercase', false)
        ->assertJsonPath('props.funding_qr_merchant_profile.application_name', 'X-Change')
        ->assertJsonCount(3, 'props.funding_qr_merchant_profile.template_options')
        ->assertJsonPath('props.funding_qr_merchant_profile.presentation_only', true)
        ->assertJsonPath('props.funding_qr_merchant_profile.controls_routing', false)
        ->assertJsonPath('props.funding_qr_merchant_profile.controls_settlement', false)
        ->assertJsonMissingPath('props.funding_read_model.provider_transaction_id')
        ->assertJsonMissingPath('props.funding_read_model.provider_request_id')
        ->assertJsonMissingPath('props.funding_read_model.funding_address')
        ->assertJsonMissingPath('props.funding_read_model.account_reference')
        ->assertJsonMissingPath('props.funding_read_model.webhook_payload');
});

it('renders the runtime profile diagnostics page as read-only configuration visibility', function () {
    config()->set('x-change.cockpit.system_readiness.visible', true);
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.diagnostics.runtime-profile'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/RuntimeProfile')
        ->assertJsonPath('props.runtime_profile_read_model.schema', 'x-change.cockpit.runtime-profile-page.v1')
        ->assertJsonPath('props.runtime_profile_read_model.status', 'available')
        ->assertJsonPath('props.runtime_profile_read_model.authorized', true)
        ->assertJsonPath('props.runtime_profile_read_model.read_only', true)
        ->assertJsonPath('props.runtime_profile_read_model.copy.title', 'System Readiness')
        ->assertJsonPath('props.runtime_profile_read_model.system_readiness.schema', 'x-change.cockpit.system-readiness.v1')
        ->assertJsonPath('props.runtime_profile_read_model.system_readiness.context.profile', fn (mixed $profile): bool => is_string($profile) && $profile !== '')
        ->assertJsonPath('props.runtime_profile_read_model.system_readiness.redactions.secrets_exposed', false)
        ->assertJsonPath('props.runtime_profile_read_model.system_readiness.redactions.performs_live_provider_checks', false)
        ->assertJsonPath('props.runtime_profile_read_model.profile.schema', 'x-change.cockpit.operator-issuance-activity-runtime-profile.v1')
        ->assertJsonPath('props.runtime_profile_read_model.safety.mutates_configuration', false)
        ->assertJsonPath('props.runtime_profile_read_model.safety.enables_handoffs', false)
        ->assertJsonPath('props.runtime_profile_read_model.safety.writes_journal', false)
        ->assertJsonPath('props.runtime_profile_read_model.safety.executes_action', false)
        ->assertJsonPath('props.runtime_profile_read_model.safety.sends_feedback', false)
        ->assertJsonPath('props.runtime_profile_read_model.safety.calls_provider', false)
        ->assertJsonPath('props.runtime_profile_read_model.safety.moves_money', false)
        ->assertJsonPath('props.runtime_profile_read_model.redactions.payloads', 'runtime-configuration-class-names-only')
        ->assertJsonPath('props.runtime_profile_read_model.commercial_accounting', null)
        ->assertJsonMissingPath('props.runtime_profile_read_model.provider_payload')
        ->assertJsonMissingPath('props.runtime_profile_read_model.raw_payload')
        ->assertJsonMissingPath('props.runtime_profile_read_model.wallet')
        ->assertJsonMissingPath('props.runtime_profile_read_model.mutation_route');
});

it('shows commercial accounting attestation only to the system principal', function () {
    config()->set('x-change.cockpit.system_readiness.visible', true);
    $system = enableNetbankTreasuryForTests();
    $this->actingAs($system);

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.diagnostics.runtime-profile'))
        ->assertOk()
        ->assertJsonPath(
            'props.runtime_profile_read_model.commercial_accounting.schema',
            'x-change.commercial-accounting-attestation.v1',
        )
        ->assertJsonPath(
            'props.runtime_profile_read_model.redactions.commercial_accounting_exposed',
            true,
        )
        ->assertJsonMissingPath('props.runtime_profile_read_model.commercial_accounting.credentials')
        ->assertJsonMissingPath('props.runtime_profile_read_model.commercial_accounting.provider_payload');
});

it('hides system readiness from authenticated users by default', function (): void {
    config()->set('x-change.cockpit.system_readiness.visible', false);
    actingAsTestUser();

    $this->get(route('x-change.cockpit.diagnostics.runtime-profile'))
        ->assertNotFound();
});

it('keeps voucher route context explicit without loading voucher payloads', function () {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.show', ['code' => 'PC-READY-001']))
        ->assertOk()
        ->assertJsonPath('props.context.code', 'PC-READY-001')
        ->assertJsonMissingPath('props.voucher')
        ->assertJsonMissingPath('props.journal')
        ->assertJsonMissingPath('props.actions')
        ->assertJsonMissingPath('props.feedback')
        ->assertJsonMissingPath('props.provider_payload');
});

it('presents not wired cockpit read models on voucher scoped pages', function (string $route) {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route($route, ['code' => 'PC-READY-001']))
        ->assertOk()
        ->assertJsonPath('props.read_model.code', 'PC-READY-001')
        ->assertJsonPath('props.read_model.voucher.status', 'not_wired')
        ->assertJsonPath('props.read_model.voucher.authorized', false)
        ->assertJsonPath('props.read_model.execution.status', 'not_wired')
        ->assertJsonPath('props.read_model.execution.execution_id', null)
        ->assertJsonPath('props.read_model.journal.status', 'not_wired')
        ->assertJsonPath('props.read_model.actions.status', 'not_wired')
        ->assertJsonPath('props.read_model.feedback.status', 'not_wired')
        ->assertJsonPath('props.read_model.voucher.redactions.payloads', 'not-loaded')
        ->assertJsonPath('props.read_model.execution.redactions.payloads', 'not-loaded')
        ->assertJsonMissingPath('props.read_model.provider_payload')
        ->assertJsonMissingPath('props.read_model.raw_payload')
        ->assertJsonMissingPath('props.read_model.wallet')
        ->assertJsonMissingPath('props.read_model.provider');
})->with([
    'voucher detail' => 'x-change.cockpit.pay-codes.show',
    'distribution workspace' => 'x-change.cockpit.pay-codes.distribution',
]);

it('hydrates the distribution workspace with read-only share surface facts', function () {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.distribution', ['code' => 'PC-READY-001']))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/DistributionWorkspace')
        ->assertJsonPath('props.context.code', 'PC-READY-001')
        ->assertJsonPath('props.distribution_workspace_read_model.schema', 'x-change.cockpit.distribution-workspace.v1')
        ->assertJsonPath('props.distribution_workspace_read_model.status', 'available')
        ->assertJsonPath('props.distribution_workspace_read_model.authorized', true)
        ->assertJsonPath('props.distribution_workspace_read_model.code', 'PC-READY-001')
        ->assertJsonPath('props.distribution_workspace_read_model.summary.code', 'PC-READY-001')
        ->assertJsonPath('props.distribution_workspace_read_model.share_assets.0.key', 'copy-text')
        ->assertJsonPath('props.distribution_workspace_read_model.share_assets.0.available', true)
        ->assertJsonPath('props.distribution_workspace_read_model.share_assets.0.read_only', true)
        ->assertJsonPath('props.distribution_workspace_read_model.share_assets.1.key', 'qr')
        ->assertJsonPath('props.distribution_workspace_read_model.share_assets.1.status', 'deferred')
        ->assertJsonPath('props.distribution_workspace_read_model.channels.0.key', 'sms')
        ->assertJsonPath('props.distribution_workspace_read_model.channels.0.source', 'feedback-read-model')
        ->assertJsonPath('props.distribution_workspace_read_model.print_templates.0.key', 'receipt-card')
        ->assertJsonPath('props.distribution_workspace_read_model.analytics.0.key', 'delivery-state')
        ->assertJsonPath('props.distribution_workspace_read_model.actions.0.key', 'send-now')
        ->assertJsonPath('props.distribution_workspace_read_model.actions.0.status', 'blocked')
        ->assertJsonPath('props.distribution_workspace_read_model.actions.0.read_only', true)
        ->assertJsonPath('props.distribution_workspace_read_model.redactions.payloads', 'distribution-read-model-summary-only')
        ->assertJsonPath('props.distribution_workspace_read_model.redactions.dispatch_enabled', false)
        ->assertJsonPath('props.distribution_workspace_read_model.redactions.artifact_generation_enabled', false)
        ->assertJsonPath('props.distribution_workspace_read_model.redactions.campaign_mutation_enabled', false)
        ->assertJsonMissingPath('props.distribution_workspace_read_model.provider_payload')
        ->assertJsonMissingPath('props.distribution_workspace_read_model.raw_payload')
        ->assertJsonMissingPath('props.distribution_workspace_read_model.wallet')
        ->assertJsonMissingPath('props.distribution_workspace_read_model.secret')
        ->assertJsonMissingPath('props.distribution_workspace_read_model.dispatch_endpoint')
        ->assertJsonMissingPath('props.distribution_workspace_read_model.mutation_route');
});

it('hydrates the pay code explorer with a sanitized list read model prop', function () {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.index'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/PayCodeExplorer')
        ->assertJsonPath('props.pay_codes_read_model.status', 'available')
        ->assertJsonPath('props.pay_codes_read_model.authorized', true)
        ->assertJsonPath('props.pay_codes_read_model.query', null)
        ->assertJsonPath('props.pay_codes_read_model.records', [])
        ->assertJsonPath('props.pay_codes_read_model.redactions.payloads', 'sanitized-list-summary-only')
        ->assertJsonPath('props.can.view_technical_details', false)
        ->assertJsonMissingPath('props.read_model')
        ->assertJsonMissingPath('props.pay_codes_read_model.provider_payload')
        ->assertJsonMissingPath('props.pay_codes_read_model.raw_payload')
        ->assertJsonMissingPath('props.pay_codes_read_model.wallet')
        ->assertJsonMissingPath('props.pay_codes_read_model.provider');
});

it('hydrates Pay Code Explorer technical details only for System Treasury', function () {
    $system = enableNetbankTreasuryForTests();

    $this->actingAs($system)
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.index'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/PayCodeExplorer')
        ->assertJsonPath('props.can.view_technical_details', true)
        ->assertJsonPath('props.read_model.journal.status', 'available')
        ->assertJsonPath('props.read_model.actions.status', 'available')
        ->assertJsonPath('props.read_model.feedback.status', 'available')
        ->assertJsonMissingPath('props.read_model.provider_payload')
        ->assertJsonMissingPath('props.read_model.raw_payload')
        ->assertJsonMissingPath('props.read_model.wallet');
});

it('passes optional campaign navigation context to the pay code explorer without registering campaign routes', function () {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.index', [
            'campaign_planning_key' => ' campaign-plan-1 ',
            'campaign_execution_id' => ' execution-1 ',
            'campaign_id' => ' campaign-1 ',
            'campaign_audience_id' => ' audience-1 ',
            'campaign_recipient_id' => ' recipient-1 ',
            'campaign_source' => ' campaign_cockpit ',
        ]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/PayCodeExplorer')
        ->assertJsonPath('props.campaign_navigation_context.schema', 'x-change.cockpit.campaign-navigation.v1')
        ->assertJsonPath('props.campaign_navigation_context.status', 'available')
        ->assertJsonPath('props.campaign_navigation_context.authorized', true)
        ->assertJsonPath('props.campaign_navigation_context.source', 'campaign_cockpit')
        ->assertJsonPath('props.campaign_navigation_context.planning_key', 'campaign-plan-1')
        ->assertJsonPath('props.campaign_navigation_context.execution_id', 'execution-1')
        ->assertJsonPath('props.campaign_navigation_context.campaign_id', 'campaign-1')
        ->assertJsonPath('props.campaign_navigation_context.audience_id', 'audience-1')
        ->assertJsonPath('props.campaign_navigation_context.recipient_id', 'recipient-1')
        ->assertJsonPath('props.campaign_navigation_context.destination', 'pay_code_explorer')
        ->assertJsonPath('props.campaign_navigation_context.read_only', true)
        ->assertJsonPath('props.campaign_navigation_context.mutation.enabled', false)
        ->assertJsonPath('props.campaign_navigation_context.mutation.status', 'blocked')
        ->assertJsonPath('props.campaign_navigation_context.mutation.reason', 'campaign-navigation-read-only')
        ->assertJsonPath('props.campaign_navigation_context.redactions.payloads', 'navigation-context-only')
        ->assertJsonPath('props.campaign_navigation_context.redactions.routes_registered', false)
        ->assertJsonPath('props.campaign_navigation_context.redactions.controllers_registered', false)
        ->assertJsonPath('props.campaign_navigation_context.redactions.mutates_campaigns', false)
        ->assertJsonPath('props.campaign_navigation_context.redactions.issues_pay_codes', false)
        ->assertJsonPath('props.campaign_navigation_context.redactions.sends_feedback', false)
        ->assertJsonPath('props.campaign_navigation_context.redactions.writes_journal', false)
        ->assertJsonPath('props.campaign_navigation_context.redactions.moves_money', false)
        ->assertJsonMissingPath('props.campaign_navigation_context.provider_payload')
        ->assertJsonMissingPath('props.campaign_navigation_context.raw_payload')
        ->assertJsonMissingPath('props.campaign_navigation_context.wallet')
        ->assertJsonMissingPath('props.campaign_navigation_context.campaign_route')
        ->assertJsonMissingPath('props.campaign_navigation_context.mutation_route')
        ->assertJsonMissingPath('props.campaign_navigation_context.campaign_mutation_endpoint')
        ->assertJsonMissingPath('props.campaign_navigation_context.pay_code_generation_payload');

    $campaignRoutes = collect(Route::getRoutes())
        ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'x-change.cockpit.campaign'));

    expect($campaignRoutes)->not->toBeEmpty()
        ->and(Route::getRoutes()->getByName('x-change.cockpit.campaigns.index'))->not->toBeNull();
});

it('passes optional campaign recipient navigation context to voucher detail and distribution destinations', function (string $route, string $destination) {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route($route, [
            'code' => 'PC-READY-001',
            'campaign_planning_key' => ' plan-wave-46 ',
            'campaign_execution_id' => ' exec-wave-46 ',
            'campaign_id' => ' campaign-wave-46 ',
            'campaign_audience_id' => ' audience-wave-46 ',
            'campaign_recipient_id' => ' recipient-wave-46 ',
            'campaign_source' => ' x_campaign_adapter ',
        ]))
        ->assertOk()
        ->assertJsonPath('props.campaign_navigation_context.schema', 'x-change.cockpit.campaign-navigation.v1')
        ->assertJsonPath('props.campaign_navigation_context.status', 'available')
        ->assertJsonPath('props.campaign_navigation_context.authorized', true)
        ->assertJsonPath('props.campaign_navigation_context.source', 'x_campaign_adapter')
        ->assertJsonPath('props.campaign_navigation_context.planning_key', 'plan-wave-46')
        ->assertJsonPath('props.campaign_navigation_context.execution_id', 'exec-wave-46')
        ->assertJsonPath('props.campaign_navigation_context.campaign_id', 'campaign-wave-46')
        ->assertJsonPath('props.campaign_navigation_context.audience_id', 'audience-wave-46')
        ->assertJsonPath('props.campaign_navigation_context.recipient_id', 'recipient-wave-46')
        ->assertJsonPath('props.campaign_navigation_context.destination', $destination)
        ->assertJsonPath('props.campaign_navigation_context.read_only', true)
        ->assertJsonPath('props.campaign_navigation_context.mutation.enabled', false)
        ->assertJsonPath('props.campaign_navigation_context.mutation.status', 'blocked')
        ->assertJsonPath('props.campaign_navigation_context.mutation.reason', 'campaign-navigation-read-only')
        ->assertJsonPath('props.campaign_navigation_context.redactions.payloads', 'navigation-context-only')
        ->assertJsonPath('props.campaign_navigation_context.redactions.routes_registered', false)
        ->assertJsonPath('props.campaign_navigation_context.redactions.controllers_registered', false)
        ->assertJsonPath('props.campaign_navigation_context.redactions.mutates_campaigns', false)
        ->assertJsonPath('props.campaign_navigation_context.redactions.issues_pay_codes', false)
        ->assertJsonPath('props.campaign_navigation_context.redactions.sends_feedback', false)
        ->assertJsonPath('props.campaign_navigation_context.redactions.writes_journal', false)
        ->assertJsonPath('props.campaign_navigation_context.redactions.moves_money', false)
        ->assertJsonMissingPath('props.campaign_navigation_context.provider_payload')
        ->assertJsonMissingPath('props.campaign_navigation_context.raw_payload')
        ->assertJsonMissingPath('props.campaign_navigation_context.wallet')
        ->assertJsonMissingPath('props.campaign_navigation_context.campaign_route')
        ->assertJsonMissingPath('props.campaign_navigation_context.mutation_route')
        ->assertJsonMissingPath('props.campaign_navigation_context.campaign_mutation_endpoint')
        ->assertJsonMissingPath('props.campaign_navigation_context.pay_code_generation_payload');
})->with([
    'voucher detail' => ['x-change.cockpit.pay-codes.show', 'pay_code_detail'],
    'distribution workspace' => ['x-change.cockpit.pay-codes.distribution', 'distribution_workspace'],
]);

it('passes optional operator activity navigation context to the pay code explorer without mutation surfaces', function () {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.index', [
            'activity_code' => ' pc-dusk-filter ',
            'activity_source' => ' operator_issuance_activity ',
        ]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/PayCodeExplorer')
        ->assertJsonPath('props.pay_codes_read_model.query', 'PC-DUSK-FILTER')
        ->assertJsonPath('props.activity_navigation_context.schema', 'x-change.cockpit.activity-navigation.v1')
        ->assertJsonPath('props.activity_navigation_context.status', 'available')
        ->assertJsonPath('props.activity_navigation_context.authorized', true)
        ->assertJsonPath('props.activity_navigation_context.source', 'operator_issuance_activity')
        ->assertJsonPath('props.activity_navigation_context.code', 'PC-DUSK-FILTER')
        ->assertJsonPath('props.activity_navigation_context.destination', 'pay_code_explorer')
        ->assertJsonPath('props.activity_navigation_context.read_only', true)
        ->assertJsonPath('props.activity_navigation_context.mutation.enabled', false)
        ->assertJsonPath('props.activity_navigation_context.mutation.status', 'blocked')
        ->assertJsonPath('props.activity_navigation_context.mutation.reason', 'activity-navigation-read-only')
        ->assertJsonPath('props.activity_navigation_context.redactions.payloads', 'activity-navigation-context-only')
        ->assertJsonPath('props.activity_navigation_context.redactions.mutates_vouchers', false)
        ->assertJsonPath('props.activity_navigation_context.redactions.executes_drivers', false)
        ->assertJsonPath('props.activity_navigation_context.redactions.writes_journal', false)
        ->assertJsonPath('props.activity_navigation_context.redactions.sends_feedback', false)
        ->assertJsonPath('props.activity_navigation_context.redactions.calls_providers', false)
        ->assertJsonPath('props.activity_navigation_context.redactions.moves_money', false)
        ->assertJsonMissingPath('props.activity_navigation_context.provider_payload')
        ->assertJsonMissingPath('props.activity_navigation_context.raw_payload')
        ->assertJsonMissingPath('props.activity_navigation_context.wallet')
        ->assertJsonMissingPath('props.activity_navigation_context.mutation_route')
        ->assertJsonMissingPath('props.activity_navigation_context.pay_code_generation_payload');
});

it('passes read-only pay code explorer search and status query filters into the read model', function () {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.index', [
            'search' => ' pc-filtered ',
            'status' => ' redeemed ',
        ]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/PayCodeExplorer')
        ->assertJsonPath('props.pay_codes_read_model.query', 'PC-FILTERED')
        ->assertJsonPath('props.pay_codes_read_model.status_filter', 'completed')
        ->assertJsonPath('props.pay_codes_read_model.stats.total', 0)
        ->assertJsonPath('props.pay_codes_read_model.stats.filtered', 0)
        ->assertJsonPath('props.pay_codes_read_model.filters.0.key', 'search')
        ->assertJsonPath('props.pay_codes_read_model.filters.0.value', 'PC-FILTERED')
        ->assertJsonPath('props.pay_codes_read_model.filters.0.active', true)
        ->assertJsonPath('props.pay_codes_read_model.filters.0.read_only', true)
        ->assertJsonPath('props.pay_codes_read_model.redactions.payloads', 'sanitized-list-summary-only')
        ->assertJsonMissingPath('props.pay_codes_read_model.provider_payload')
        ->assertJsonMissingPath('props.pay_codes_read_model.raw_payload')
        ->assertJsonMissingPath('props.pay_codes_read_model.wallet')
        ->assertJsonMissingPath('props.pay_codes_read_model.mutation_route');
});

it('keeps campaign context while applying pay code explorer search and status query filters', function () {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.index', [
            'search' => ' pc-campaign ',
            'status' => ' active ',
            'campaign_planning_key' => ' plan-filter-1 ',
            'campaign_execution_id' => ' exec-filter-1 ',
            'campaign_id' => ' campaign-filter-1 ',
            'campaign_audience_id' => ' audience-filter-1 ',
            'campaign_recipient_id' => ' recipient-filter-1 ',
            'campaign_source' => ' x_campaign_adapter ',
        ]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/PayCodeExplorer')
        ->assertJsonPath('props.pay_codes_read_model.query', 'PC-CAMPAIGN')
        ->assertJsonPath('props.pay_codes_read_model.status_filter', 'active')
        ->assertJsonPath('props.pay_codes_read_model.filters.0.key', 'search')
        ->assertJsonPath('props.pay_codes_read_model.filters.0.value', 'PC-CAMPAIGN')
        ->assertJsonPath('props.pay_codes_read_model.filters.0.active', true)
        ->assertJsonPath('props.pay_codes_read_model.filters.0.read_only', true)
        ->assertJsonPath('props.pay_codes_read_model.filters.2.key', 'status')
        ->assertJsonPath('props.pay_codes_read_model.filters.2.value', 'active')
        ->assertJsonPath('props.pay_codes_read_model.filters.2.active', true)
        ->assertJsonPath('props.pay_codes_read_model.filters.2.read_only', true)
        ->assertJsonPath('props.campaign_navigation_context.planning_key', 'plan-filter-1')
        ->assertJsonPath('props.campaign_navigation_context.execution_id', 'exec-filter-1')
        ->assertJsonPath('props.campaign_navigation_context.campaign_id', 'campaign-filter-1')
        ->assertJsonPath('props.campaign_navigation_context.audience_id', 'audience-filter-1')
        ->assertJsonPath('props.campaign_navigation_context.recipient_id', 'recipient-filter-1')
        ->assertJsonPath('props.campaign_navigation_context.source', 'x_campaign_adapter')
        ->assertJsonPath('props.campaign_navigation_context.destination', 'pay_code_explorer')
        ->assertJsonPath('props.campaign_navigation_context.read_only', true)
        ->assertJsonPath('props.campaign_navigation_context.mutation.enabled', false)
        ->assertJsonPath('props.campaign_navigation_context.redactions.payloads', 'navigation-context-only')
        ->assertJsonMissingPath('props.campaign_navigation_context.raw_payload')
        ->assertJsonMissingPath('props.campaign_navigation_context.provider_payload')
        ->assertJsonMissingPath('props.campaign_navigation_context.wallet')
        ->assertJsonMissingPath('props.campaign_navigation_context.mutation_route')
        ->assertJsonMissingPath('props.campaign_navigation_context.pay_code_generation_payload');
});

it('hydrates the dashboard with a sanitized dashboard read model prop', function () {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Dashboard')
        ->assertJsonPath('props.dashboard_read_model.status', 'available')
        ->assertJsonPath('props.dashboard_read_model.authorized', true)
        ->assertJsonPath('props.dashboard_read_model.metrics.0.key', 'pay-codes-visible')
        ->assertJsonPath('props.dashboard_read_model.metrics.0.value', '0')
        ->assertJsonPath('props.dashboard_read_model.pipeline.0.key', 'issued')
        ->assertJsonPath('props.dashboard_read_model.pipeline.0.value', '0')
        ->assertJsonPath('props.dashboard_read_model.risk_signals.0.key', 'expired-pay-codes')
        ->assertJsonPath('props.dashboard_read_model.risk_signals.0.value', '0 sanitized summaries')
        ->assertJsonPath('props.dashboard_read_model.activity', [])
        ->assertJsonPath('props.dashboard_read_model.redactions.payloads', 'sanitized-dashboard-summary-only')
        ->assertJsonMissingPath('props.dashboard_read_model.provider_payload')
        ->assertJsonMissingPath('props.dashboard_read_model.raw_payload')
        ->assertJsonMissingPath('props.dashboard_read_model.wallet')
        ->assertJsonMissingPath('props.dashboard_read_model.provider');
});

it('exposes a read-only campaign cockpit read model prop on the dashboard route', function () {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Dashboard')
        ->assertJsonPath('props.campaign_read_model.schema', 'x-change.cockpit.campaign-adoption.v1')
        ->assertJsonPath('props.campaign_read_model.status', 'available')
        ->assertJsonPath('props.campaign_read_model.authorized', true)
        ->assertJsonPath('props.campaign_read_model.source', 'x-campaign')
        ->assertJsonPath('props.campaign_read_model.facts.context_status', 'no-campaign-selected')
        ->assertJsonPath('props.campaign_read_model.facts.selected', false)
        ->assertJsonPath('props.campaign_read_model.facts.metadata.package_available', true)
        ->assertJsonPath('props.campaign_read_model.mutation.enabled', false)
        ->assertJsonPath('props.campaign_read_model.mutation.status', 'blocked')
        ->assertJsonPath('props.campaign_read_model.mutation.reason', 'campaign-mutations-not-authorized')
        ->assertJsonPath('props.campaign_read_model.redactions.payloads', 'campaign-cockpit-package-presence-only')
        ->assertJsonPath('props.campaign_read_model.redactions.source', 'x-campaign')
        ->assertJsonPath('props.campaign_read_model.redactions.reason', 'no-campaign-selected')
        ->assertJsonPath('props.campaign_read_model.redactions.read_only', true)
        ->assertJsonPath('props.campaign_read_model.redactions.mutates_campaigns', false)
        ->assertJsonPath('props.campaign_read_model.redactions.issues_pay_codes', false)
        ->assertJsonPath('props.campaign_read_model.redactions.sends_feedback', false)
        ->assertJsonPath('props.campaign_read_model.redactions.writes_journal', false)
        ->assertJsonPath('props.campaign_read_model.redactions.moves_money', false)
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.enabled', false)
        ->assertJsonMissingPath('props.campaign_read_model.provider_payload')
        ->assertJsonMissingPath('props.campaign_read_model.raw_payload')
        ->assertJsonMissingPath('props.campaign_read_model.wallet')
        ->assertJsonMissingPath('props.campaign_read_model.campaign_mutation_endpoint')
        ->assertJsonMissingPath('props.campaign_read_model.pay_code_generation_payload')
        ->assertJsonMissingPath('props.campaign_read_model.delivery_dispatch_payload')
        ->assertJsonMissingPath('props.campaign_read_model.mutation_route');
});

it('exposes operator issuance activity presentation read models on the dashboard route', function () {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Dashboard')
        ->assertJsonPath('props.operator_issuance_activity_read_model.schema', 'x-change.cockpit.operator-issuance-activity.v1')
        ->assertJsonPath('props.operator_issuance_activity_read_model.status', 'not_wired')
        ->assertJsonPath('props.operator_issuance_activity_read_model.authorized', false)
        ->assertJsonPath('props.operator_issuance_activity_read_model.items', [])
        ->assertJsonPath('props.operator_issuance_activity_read_model.presentations', [])
        ->assertJsonPath('props.operator_issuance_activity_read_model.redactions.payloads', 'activity-summary-only')
        ->assertJsonPath('props.operator_issuance_activity_read_model.redactions.lifecycle_truth', false)
        ->assertJsonPath('props.operator_issuance_activity_read_model.redactions.writes_journal', false)
        ->assertJsonPath('props.operator_issuance_activity_read_model.redactions.executes_actions', false)
        ->assertJsonPath('props.operator_issuance_activity_read_model.redactions.sends_feedback', false)
        ->assertJsonPath('props.operator_issuance_activity_read_model.redactions.moves_money', false)
        ->assertJsonMissingPath('props.operator_issuance_activity_read_model.provider_payload')
        ->assertJsonMissingPath('props.operator_issuance_activity_read_model.raw_payload')
        ->assertJsonMissingPath('props.operator_issuance_activity_read_model.wallet')
        ->assertJsonMissingPath('props.operator_issuance_activity_read_model.provider');
});

it('passes read-only operator activity search filter query parameters to the dashboard read model', function () {
    $operator = actingAsTestUser();

    config()->set('x-change.cockpit.operator_issuance_activity.repository', DatabaseCockpitOperatorIssuanceActivityRepository::class);

    $repository = app(DatabaseCockpitOperatorIssuanceActivityRepository::class);

    $repository->record(new CockpitOperatorIssuanceActivityRecordData(
        activity_id: 'activity-dashboard-filter-1',
        actor_id: (string) $operator->getAuthIdentifier(),
        source: 'cockpit.quick-generate',
        subject_type: 'pay_code',
        subject_reference: 'PC-DASHBOARD-FILTER-1',
        status: 'issued',
        occurred_at: '2026-07-10T09:00:00+08:00',
        correlation_id: 'corr-dashboard-filter-1',
        summary: 'Money Changer Pay Code issued',
        safe_context: [
            'amount' => '25',
            'currency' => 'PHP',
            'route' => 'x-change.cockpit.quick-generate.store',
            'detail_href' => '/x/cockpit/pay-codes/PC-DASHBOARD-FILTER-1',
        ],
        journal_handoff_status: 'recorded',
    ));

    $repository->record(new CockpitOperatorIssuanceActivityRecordData(
        activity_id: 'activity-dashboard-filter-2',
        actor_id: (string) $operator->getAuthIdentifier(),
        source: 'cockpit.quick-generate',
        subject_type: 'pay_code',
        subject_reference: 'PC-DASHBOARD-FILTER-2',
        status: 'failed',
        occurred_at: '2026-07-10T09:05:00+08:00',
        correlation_id: 'corr-dashboard-filter-2',
        summary: 'Failed issuance activity',
    ));

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard', [
            'activity_search' => 'money changer',
            'activity_status' => ['issued'],
            'activity_handoff_status' => ['recorded'],
        ]))
        ->assertOk()
        ->assertJsonPath('props.operator_issuance_activity_read_model.items.0.id', 'activity-dashboard-filter-1')
        ->assertJsonPath('props.operator_issuance_activity_read_model.search_filters.search', 'money changer')
        ->assertJsonPath('props.operator_issuance_activity_read_model.search_filters.statuses.0', 'issued')
        ->assertJsonPath('props.operator_issuance_activity_read_model.search_filters.handoff_statuses.0', 'recorded')
        ->assertJsonPath('props.operator_issuance_activity_read_model.search_filters.safety.read_only', true)
        ->assertJsonPath('props.operator_issuance_activity_read_model.search_filters.safety.moves_money', false)
        ->assertJsonMissingPath('props.operator_issuance_activity_read_model.provider_payload')
        ->assertJsonMissingPath('props.operator_issuance_activity_read_model.raw_payload')
        ->assertJsonMissingPath('props.operator_issuance_activity_read_model.mutation_route');
});

it('passes optional campaign context from the dashboard route to the read-only campaign adapter', function () {
    $operator = actingAsTestUser();

    $service = new class
    {
        /**
         * @var array<string, mixed>
         */
        public array $received = [];

        /**
         * @param  array<string, mixed>  $metadata
         * @return array<string, mixed>
         */
        public function summary(
            string $planningKey,
            string $executionId,
            string $operatorId,
            string $channel = 'sms',
            ?string $correlationId = null,
            array $metadata = [],
        ): array {
            $this->received = compact('planningKey', 'executionId', 'operatorId', 'channel', 'correlationId', 'metadata');

            return [
                'status' => 'ready',
                'planning_key' => $planningKey,
                'execution_id' => $executionId,
                'operator_id' => $operatorId,
                'cards' => [
                    'campaign' => [
                        'name' => 'Food Aid July',
                        'recipient_count' => 250,
                        'secret' => 'do-not-show',
                    ],
                ],
                'panels' => [
                    'audience_import_workspace' => ['status' => 'ready'],
                ],
                'actions' => [
                    'review_campaign' => ['enabled' => true],
                    'generate_pay_codes' => ['enabled' => false],
                ],
                'effects' => [
                    'persists' => false,
                    'uses_database' => false,
                    'queues_jobs' => false,
                    'issues_pay_codes' => false,
                    'sends_feedback' => false,
                    'writes_journal' => false,
                    'moves_money' => false,
                ],
                'metadata' => [
                    'source' => 'fake-x-campaign',
                    'read_only' => true,
                    'token' => 'sensitive-token',
                ],
            ];
        }
    };

    config(['x-change.cockpit.integrations.campaign.cockpit' => 'fake.route.campaign.cockpit']);
    app()->instance('fake.route.campaign.cockpit', $service);

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard', [
            'campaign_planning_key' => ' campaign-plan-1 ',
            'campaign_execution_id' => ' execution-1 ',
        ]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Dashboard')
        ->assertJsonPath('props.campaign_read_model.status', 'available')
        ->assertJsonPath('props.campaign_read_model.authorized', true)
        ->assertJsonPath('props.campaign_read_model.source', 'x-campaign')
        ->assertJsonPath('props.campaign_read_model.facts.planning_key', 'campaign-plan-1')
        ->assertJsonPath('props.campaign_read_model.facts.execution_id', 'execution-1')
        ->assertJsonPath('props.campaign_read_model.facts.operator_id', (string) $operator->getAuthIdentifier())
        ->assertJsonPath('props.campaign_read_model.facts.cards.campaign.name', 'Food Aid July')
        ->assertJsonPath('props.campaign_read_model.facts.cards.campaign.recipient_count', 250)
        ->assertJsonPath('props.campaign_read_model.facts.cards.campaign.secret', '[redacted]')
        ->assertJsonPath('props.campaign_read_model.facts.metadata.token', '[redacted]')
        ->assertJsonPath('props.campaign_read_model.mutation.enabled', false)
        ->assertJsonPath('props.campaign_read_model.redactions.payloads', 'campaign-cockpit-summary-only')
        ->assertJsonPath('props.campaign_read_model.redactions.routes_registered', false)
        ->assertJsonPath('props.campaign_read_model.redactions.controllers_registered', false)
        ->assertJsonPath('props.campaign_read_model.redactions.mutates_campaigns', false)
        ->assertJsonPath('props.campaign_read_model.redactions.issues_pay_codes', false)
        ->assertJsonPath('props.campaign_read_model.redactions.sends_feedback', false)
        ->assertJsonPath('props.campaign_read_model.redactions.writes_journal', false)
        ->assertJsonPath('props.campaign_read_model.redactions.moves_money', false)
        ->assertJsonMissingPath('props.campaign_read_model.provider_payload')
        ->assertJsonMissingPath('props.campaign_read_model.raw_payload')
        ->assertJsonMissingPath('props.campaign_read_model.wallet')
        ->assertJsonMissingPath('props.campaign_read_model.campaign_mutation_endpoint')
        ->assertJsonMissingPath('props.campaign_read_model.pay_code_generation_payload')
        ->assertJsonMissingPath('props.campaign_read_model.delivery_dispatch_payload')
        ->assertJsonMissingPath('props.campaign_read_model.mutation_route');

    expect($service->received)->toMatchArray([
        'planningKey' => 'campaign-plan-1',
        'executionId' => 'execution-1',
        'operatorId' => (string) $operator->getAuthIdentifier(),
        'channel' => 'cockpit',
        'correlationId' => 'execution-1',
        'metadata' => [
            'source' => 'x-change.cockpit',
            'read_only' => true,
            'integration' => 'campaign.cockpit',
        ],
    ]);
});

it('hydrates quick generate with a sanitized quick generate read model prop', function () {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.quick-generate'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/QuickGenerate')
        ->assertJsonPath('props.quick_generate_read_model.status', 'available')
        ->assertJsonPath('props.quick_generate_read_model.authorized', true)
        ->assertJsonPath('props.quick_generate_read_model.templates.0.key', 'money-changer')
        ->assertJsonPath('props.quick_generate_read_model.templates.0.disabled', false)
        ->assertJsonPath('props.quick_generate_read_model.runtime_inputs.0.key', 'amount')
        ->assertJsonPath('props.quick_generate_read_model.pricing_summaries.0.key', 'pricing')
        ->assertJsonPath('props.quick_generate_read_model.pricing_gate.status', 'runtime-informational')
        ->assertJsonPath('props.quick_generate_read_model.pricing_gate.checks.0.key', 'template-selected')
        ->assertJsonPath('props.quick_generate_read_model.pricing_gate.checks.0.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.pricing_gate.checks.1.key', 'amount-input-present')
        ->assertJsonPath('props.quick_generate_read_model.pricing_gate.checks.1.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.pricing_gate.checks.2.key', 'pricing-service-wired')
        ->assertJsonPath('props.quick_generate_read_model.pricing_gate.checks.3.key', 'funding-source-selected')
        ->assertJsonPath('props.quick_generate_read_model.pricing_gate.checks.4.key', 'funds-reservation')
        ->assertJsonPath('props.quick_generate_read_model.pricing_gate.checks.5.key', 'provider-fee-quote')
        ->assertJsonPath('props.quick_generate_read_model.pricing_gate.redactions.payloads', 'pricing-gates-only')
        ->assertJsonPath('props.quick_generate_read_model.funding_gate.status', 'runtime-informational')
        ->assertJsonPath('props.quick_generate_read_model.funding_gate.checks.0.key', 'funding-policy-known')
        ->assertJsonPath('props.quick_generate_read_model.funding_gate.checks.0.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.funding_gate.checks.1.key', 'issuer-wallet-identified')
        ->assertJsonPath('props.quick_generate_read_model.funding_gate.checks.1.status', 'runtime-diagnostic')
        ->assertJsonPath('props.quick_generate_read_model.funding_gate.checks.2.key', 'wallet-balance-available')
        ->assertJsonPath('props.quick_generate_read_model.funding_gate.checks.3.key', 'sufficient-funds')
        ->assertJsonPath('props.quick_generate_read_model.funding_gate.checks.4.key', 'funds-reservation-ready')
        ->assertJsonPath('props.quick_generate_read_model.funding_gate.checks.5.key', 'provider-funding-ready')
        ->assertJsonPath('props.quick_generate_read_model.funding_gate.redactions.payloads', 'funding-gates-only')
        ->assertJsonPath('props.quick_generate_read_model.idempotency_gate.status', 'backend-ready')
        ->assertJsonPath('props.quick_generate_read_model.idempotency_gate.checks.0.key', 'idempotency-policy-known')
        ->assertJsonPath('props.quick_generate_read_model.idempotency_gate.checks.0.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.idempotency_gate.checks.1.key', 'idempotency-key-source-defined')
        ->assertJsonPath('props.quick_generate_read_model.idempotency_gate.checks.1.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.idempotency_gate.checks.2.key', 'payload-fingerprint-defined')
        ->assertJsonPath('props.quick_generate_read_model.idempotency_gate.checks.2.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.idempotency_gate.checks.3.key', 'replay-lookup-ready')
        ->assertJsonPath('props.quick_generate_read_model.idempotency_gate.checks.3.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.idempotency_gate.checks.4.key', 'conflict-response-ready')
        ->assertJsonPath('props.quick_generate_read_model.idempotency_gate.checks.4.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.idempotency_gate.checks.5.key', 'ttl-policy-ready')
        ->assertJsonPath('props.quick_generate_read_model.idempotency_gate.checks.5.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.idempotency_gate.redactions.payloads', 'idempotency-gates-only')
        ->assertJsonPath('props.quick_generate_read_model.validation_redaction_gate.status', 'backend-ready')
        ->assertJsonPath('props.quick_generate_read_model.validation_redaction_gate.checks.0.key', 'request-schema-known')
        ->assertJsonPath('props.quick_generate_read_model.validation_redaction_gate.checks.0.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.validation_redaction_gate.checks.1.key', 'required-fields-defined')
        ->assertJsonPath('props.quick_generate_read_model.validation_redaction_gate.checks.1.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.validation_redaction_gate.checks.2.key', 'validation-rules-wired')
        ->assertJsonPath('props.quick_generate_read_model.validation_redaction_gate.checks.3.key', 'sensitive-fields-redacted')
        ->assertJsonPath('props.quick_generate_read_model.validation_redaction_gate.checks.4.key', 'sanitized-preview-ready')
        ->assertJsonPath('props.quick_generate_read_model.validation_redaction_gate.checks.5.key', 'validation-error-contract-ready')
        ->assertJsonPath('props.quick_generate_read_model.validation_redaction_gate.redactions.payloads', 'validation-redaction-gates-only')
        ->assertJsonPath('props.quick_generate_read_model.mutation_handoff_plan.status', 'backend-handoff-wired')
        ->assertJsonPath('props.quick_generate_read_model.mutation_handoff_plan.steps.0.key', 'existing-issuance-owner-identified')
        ->assertJsonPath('props.quick_generate_read_model.mutation_handoff_plan.steps.0.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.mutation_handoff_plan.steps.1.key', 'generate-pay-code-action-handoff')
        ->assertJsonPath('props.quick_generate_read_model.mutation_handoff_plan.steps.1.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.mutation_handoff_plan.steps.2.key', 'generate-pay-code-controller-handoff')
        ->assertJsonPath('props.quick_generate_read_model.mutation_handoff_plan.steps.2.status', 'confirmed')
        ->assertJsonPath('props.quick_generate_read_model.mutation_handoff_plan.steps.3.key', 'preconditions-green')
        ->assertJsonPath('props.quick_generate_read_model.mutation_handoff_plan.steps.4.key', 'side-effect-boundary-confirmed')
        ->assertJsonPath('props.quick_generate_read_model.mutation_handoff_plan.steps.4.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.mutation_handoff_plan.steps.5.key', 'operator-response-contract-ready')
        ->assertJsonPath('props.quick_generate_read_model.mutation_handoff_plan.steps.5.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.mutation_handoff_plan.redactions.payloads', 'mutation-handoff-plan-only')
        ->assertJsonPath('props.quick_generate_read_model.mutation_preconditions_review.status', 'existing-handoff-ready')
        ->assertJsonPath('props.quick_generate_read_model.mutation_preconditions_review.recommendation', 'use-existing-issuance-handoff')
        ->assertJsonPath('props.quick_generate_read_model.mutation_preconditions_review.items.0.key', 'authorization-ready')
        ->assertJsonPath('props.quick_generate_read_model.mutation_preconditions_review.items.0.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.mutation_preconditions_review.items.1.key', 'pricing-ready')
        ->assertJsonPath('props.quick_generate_read_model.mutation_preconditions_review.items.2.key', 'funding-ready')
        ->assertJsonPath('props.quick_generate_read_model.mutation_preconditions_review.items.3.key', 'idempotency-ready')
        ->assertJsonPath('props.quick_generate_read_model.mutation_preconditions_review.items.3.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.mutation_preconditions_review.items.4.key', 'validation-redaction-ready')
        ->assertJsonPath('props.quick_generate_read_model.mutation_preconditions_review.items.5.key', 'handoff-ready')
        ->assertJsonPath('props.quick_generate_read_model.mutation_preconditions_review.items.5.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.mutation_preconditions_review.items.6.key', 'operator-response-ready')
        ->assertJsonPath('props.quick_generate_read_model.mutation_preconditions_review.redactions.payloads', 'mutation-preconditions-review-only')
        ->assertJsonPath('props.quick_generate_read_model.mutation_authorization_decision.status', 'approved-handoff')
        ->assertJsonPath('props.quick_generate_read_model.mutation_authorization_decision.decision', 'authorized_existing_handoff')
        ->assertJsonPath('props.quick_generate_read_model.mutation_authorization_decision.required_approval', 'completed-for-existing-generate-pay-code-handoff')
        ->assertJsonPath('props.quick_generate_read_model.mutation_authorization_decision.rationale', 'Cockpit may submit Quick Generate through the existing GeneratePayCode action without inventing a parallel issuance runtime.')
        ->assertJsonPath('props.quick_generate_read_model.mutation_authorization_decision.next_step', 'keep-provider-journal-action-feedback-mutations-separately-gated')
        ->assertJsonPath('props.quick_generate_read_model.mutation_authorization_decision.redactions.payloads', 'mutation-authorization-decision-only')
        ->assertJsonPath('props.quick_generate_read_model.mutation_contract.schema', 'x-change.cockpit.quick-generate-mutation.v1')
        ->assertJsonPath('props.quick_generate_read_model.mutation_contract.status', 'existing_issuance_handoff_registered')
        ->assertJsonPath('props.quick_generate_read_model.mutation_contract.authorization', 'operator-authenticated-handoff-route')
        ->assertJsonPath('props.quick_generate_read_model.mutation_contract.route', 'x-change.cockpit.quick-generate.store')
        ->assertJsonPath('props.quick_generate_read_model.mutation_contract.route_url', '/x/cockpit/quick-generate')
        ->assertJsonPath('props.quick_generate_read_model.mutation_contract.request_adapter', 'GeneratePayCodeRequest-compatible-adapter')
        ->assertJsonPath('props.quick_generate_read_model.mutation_contract.issuance_owner', 'GeneratePayCode')
        ->assertJsonPath('props.quick_generate_read_model.mutation_contract.idempotency', 'replay-safe-route-registered')
        ->assertJsonPath('props.quick_generate_read_model.mutation_contract.response_contract', 'operator-safe-redacted-result')
        ->assertJsonPath('props.quick_generate_read_model.mutation_contract.runtime_enabled', true)
        ->assertJsonPath('props.quick_generate_read_model.mutation_contract.gates.0.key', 'route-contract-defined')
        ->assertJsonPath('props.quick_generate_read_model.mutation_contract.gates.0.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.mutation_contract.gates.1.key', 'request-adapter-defined')
        ->assertJsonPath('props.quick_generate_read_model.mutation_contract.gates.1.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.mutation_contract.gates.2.key', 'issuance-owner-confirmed')
        ->assertJsonPath('props.quick_generate_read_model.mutation_contract.gates.2.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.mutation_contract.gates.3.key', 'idempotency-required')
        ->assertJsonPath('props.quick_generate_read_model.mutation_contract.gates.3.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.mutation_contract.gates.4.key', 'operator-response-redacted')
        ->assertJsonPath('props.quick_generate_read_model.mutation_contract.gates.4.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.mutation_contract.gates.5.key', 'ui-submit-disabled')
        ->assertJsonPath('props.quick_generate_read_model.mutation_contract.gates.5.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.mutation_contract.allowed_methods.0', 'GET')
        ->assertJsonPath('props.quick_generate_read_model.mutation_contract.allowed_methods.1', 'POST')
        ->assertJsonPath('props.quick_generate_read_model.mutation_contract.redactions.payloads', 'mutation-contract-only')
        ->assertJsonPath('props.quick_generate_read_model.draft_contract.schema', 'x-change.cockpit.quick-generate-draft.v1')
        ->assertJsonPath('props.quick_generate_read_model.draft_contract.status', 'draft_only')
        ->assertJsonPath('props.quick_generate_read_model.draft_contract.template_key', 'money-changer')
        ->assertJsonPath('props.quick_generate_read_model.draft_contract.amount', null)
        ->assertJsonPath('props.quick_generate_read_model.draft_contract.currency', 'PHP')
        ->assertJsonPath('props.quick_generate_read_model.draft_contract.recipient_reference', null)
        ->assertJsonPath('props.quick_generate_read_model.draft_contract.idempotency_key', null)
        ->assertJsonPath('props.quick_generate_read_model.draft_contract.redactions.payloads', 'draft-shape-only')
        ->assertJsonPath('props.quick_generate_read_model.authorization.status', 'runtime-ready')
        ->assertJsonPath('props.quick_generate_read_model.authorization.gates.0.key', 'operator-authenticated')
        ->assertJsonPath('props.quick_generate_read_model.authorization.gates.0.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.authorization.gates.2.key', 'can-generate-pay-code')
        ->assertJsonPath('props.quick_generate_read_model.authorization.gates.2.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.authorization.gates.3.key', 'can-call-providers')
        ->assertJsonPath('props.quick_generate_read_model.authorization.gates.4.key', 'can-move-money')
        ->assertJsonPath('props.quick_generate_read_model.authorization.redactions.payloads', 'authorization-gates-only')
        ->assertJsonPath('props.quick_generate_read_model.action.enabled', true)
        ->assertJsonPath('props.quick_generate_read_model.action.reason', 'existing-issuance-handoff-enabled')
        ->assertJsonPath('props.quick_generate_read_model.redactions.payloads', 'sanitized-quick-generate-catalog-only')
        ->assertJsonMissingPath('props.quick_generate_read_model.provider_payload')
        ->assertJsonMissingPath('props.quick_generate_read_model.raw_payload')
        ->assertJsonMissingPath('props.quick_generate_read_model.wallet')
        ->assertJsonMissingPath('props.quick_generate_read_model.balance')
        ->assertJsonMissingPath('props.quick_generate_read_model.available_balance')
        ->assertJsonMissingPath('props.quick_generate_read_model.provider_wallet')
        ->assertJsonMissingPath('props.quick_generate_read_model.funding_source')
        ->assertJsonMissingPath('props.quick_generate_read_model.idempotency_key')
        ->assertJsonMissingPath('props.quick_generate_read_model.payload_fingerprint')
        ->assertJsonMissingPath('props.quick_generate_read_model.stored_response')
        ->assertJsonMissingPath('props.quick_generate_read_model.replay_payload')
        ->assertJsonMissingPath('props.quick_generate_read_model.cache_key')
        ->assertJsonMissingPath('props.quick_generate_read_model.request_payload')
        ->assertJsonMissingPath('props.quick_generate_read_model.validated_payload')
        ->assertJsonMissingPath('props.quick_generate_read_model.validation_errors')
        ->assertJsonMissingPath('props.quick_generate_read_model.mobile')
        ->assertJsonMissingPath('props.quick_generate_read_model.email')
        ->assertJsonMissingPath('props.quick_generate_read_model.recipient_reference')
        ->assertJsonMissingPath('props.quick_generate_read_model.account_number')
        ->assertJsonMissingPath('props.quick_generate_read_model.mutation_payload')
        ->assertJsonMissingPath('props.quick_generate_read_model.issued_voucher')
        ->assertJsonMissingPath('props.quick_generate_read_model.generated_pay_code')
        ->assertJsonMissingPath('props.quick_generate_read_model.side_effect_result')
        ->assertJsonMissingPath('props.quick_generate_read_model.precondition_payload')
        ->assertJsonMissingPath('props.quick_generate_read_model.mutation_approval')
        ->assertJsonMissingPath('props.quick_generate_read_model.approval_payload')
        ->assertJsonMissingPath('props.quick_generate_read_model.route_definition')
        ->assertJsonMissingPath('props.quick_generate_read_model.mutation_route')
        ->assertJsonMissingPath('props.quick_generate_read_model.mutation_contract.request_payload')
        ->assertJsonMissingPath('props.quick_generate_read_model.mutation_contract.validated_payload')
        ->assertJsonMissingPath('props.quick_generate_read_model.mutation_contract.idempotency_key')
        ->assertJsonMissingPath('props.quick_generate_read_model.mutation_contract.payload_fingerprint')
        ->assertJsonMissingPath('props.quick_generate_read_model.mutation_contract.issued_voucher')
        ->assertJsonMissingPath('props.quick_generate_read_model.mutation_contract.generated_pay_code')
        ->assertJsonMissingPath('props.quick_generate_read_model.mutation_contract.provider_payload')
        ->assertJsonMissingPath('props.quick_generate_read_model.mutation_contract.wallet')
        ->assertJsonMissingPath('props.quick_generate_read_model.mutation_contract.raw_payload');
});

it('hands quick generate mutation requests to the existing issuance action with redacted operator output', function () {
    expect(Route::has('x-change.cockpit.quick-generate.store'))->toBeTrue();

    $fakeGeneratePayCode = new class extends GeneratePayCode
    {
        /**
         * @var array<int, array<string, mixed>>
         */
        public array $payloads = [];

        public function __construct() {}

        /**
         * @param  array<string, mixed>  $input
         */
        public function handle(array $input): GeneratePayCodeResultData
        {
            $this->payloads[] = $input;

            return new GeneratePayCodeResultData(
                voucher_id: 12345,
                code: 'PC-COCKPIT-001',
                amount: $input['cash']['amount'],
                currency: $input['cash']['currency'],
                issuer: new IssuerData(id: data_get($input, 'metadata.issuer_id')),
                cost: new PricingEstimateData(currency: 'PHP', base_fee: 1.25, total: 1.25),
                wallet: [
                    'balance_before' => 100000,
                    'balance_after' => 99975,
                ],
                debit: new DebitData(id: 987, amount: 25),
                links: new PayCodeLinksData(
                    redeem: 'https://example.test/r/PC-COCKPIT-001',
                    redeem_path: '/r/PC-COCKPIT-001',
                ),
                allocations: [
                    ['internal' => 'must-not-leak'],
                ],
            );
        }
    };

    app()->instance(GeneratePayCode::class, $fakeGeneratePayCode);

    actingAsTestUser();

    $this->withHeaders([
        'Accept' => 'application/json',
        'X-Correlation-ID' => 'correlation-cockpit-1',
    ])
        ->post(route('x-change.cockpit.quick-generate.store'), [
            'cash' => [
                'amount' => 25,
                'currency' => 'PHP',
            ],
            'inputs' => [
                'fields' => [],
            ],
            'feedback' => [
                'mobile' => null,
            ],
            'rider' => [
                'message' => null,
            ],
            'secret' => 'must-not-leak',
        ])
        ->assertCreated()
        ->assertJsonPath('schema', 'x-change.cockpit.quick-generate-existing-issuance-handoff.v1')
        ->assertJsonPath('status', 'issued')
        ->assertJsonPath('authorized', true)
        ->assertJsonPath('mutation_enabled', true)
        ->assertJsonPath('runtime_enabled', true)
        ->assertJsonPath('route', 'x-change.cockpit.quick-generate.store')
        ->assertJsonPath('validation.executed', true)
        ->assertJsonPath('handoff.target', 'GeneratePayCode')
        ->assertJsonPath('handoff.controller', 'GeneratePayCodeController')
        ->assertJsonPath('handoff.executed', true)
        ->assertJsonPath('handoff.controller_invoked', false)
        ->assertJsonPath('idempotency.status', 'key-not-provided')
        ->assertJsonPath('idempotency.key', null)
        ->assertJsonPath('idempotency.persisted', false)
        ->assertJsonPath('idempotency.fingerprinted', false)
        ->assertJsonPath('idempotency.replay_checked', false)
        ->assertJsonPath('idempotency.replayed', false)
        ->assertJsonPath('result.code', 'PC-COCKPIT-001')
        ->assertJsonPath('result.amount', 25)
        ->assertJsonPath('result.currency', 'PHP')
        ->assertJsonPath('result.links.redeem_path', '/r/PC-COCKPIT-001')
        ->assertJsonPath('result.links.cockpit_detail', '/x/cockpit/pay-codes/PC-COCKPIT-001')
        ->assertJsonPath('redactions.payloads', 'operator-safe-generated-facts-only')
        ->assertJsonMissingPath('request_payload')
        ->assertJsonMissingPath('validated_payload')
        ->assertJsonMissingPath('result.voucher_id')
        ->assertJsonMissingPath('result.issuer')
        ->assertJsonMissingPath('result.wallet')
        ->assertJsonMissingPath('result.debit')
        ->assertJsonMissingPath('result.cost')
        ->assertJsonMissingPath('result.allocations')
        ->assertJsonMissingPath('issued_voucher')
        ->assertJsonMissingPath('generated_pay_code')
        ->assertJsonMissingPath('provider_payload')
        ->assertJsonMissingPath('wallet')
        ->assertJsonMissingPath('debit')
        ->assertJsonMissingPath('allocations')
        ->assertJsonMissingPath('cost')
        ->assertJsonMissingPath('raw_payload')
        ->assertJsonMissing(['must-not-leak']);

    expect($fakeGeneratePayCode->payloads)->toHaveCount(1)
        ->and($fakeGeneratePayCode->payloads[0])->not->toHaveKey('secret')
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'cash.validation'))->toBe([])
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'count'))->toBe(1)
        ->and($fakeGeneratePayCode->payloads[0]['_meta'])->toMatchArray([
            'idempotency_key' => null,
            'correlation_id' => 'correlation-cockpit-1',
            'source' => 'cockpit.quick-generate',
        ])
        ->and(data_get($fakeGeneratePayCode->payloads[0], 'metadata.issuer_id'))->not->toBeNull();
});

it('replays quick generate mutation responses for the same idempotency key and payload', function () {
    $fakeGeneratePayCode = new class extends GeneratePayCode
    {
        public int $calls = 0;

        public function __construct() {}

        /**
         * @param  array<string, mixed>  $input
         */
        public function handle(array $input): GeneratePayCodeResultData
        {
            $this->calls++;

            return new GeneratePayCodeResultData(
                voucher_id: 12345,
                code: 'PC-COCKPIT-IDEM',
                amount: $input['cash']['amount'],
                currency: $input['cash']['currency'],
                issuer: new IssuerData(id: data_get($input, 'metadata.issuer_id')),
                cost: new PricingEstimateData(currency: 'PHP', total: 1.25),
                wallet: [
                    'balance_before' => 100000,
                    'balance_after' => 99975,
                ],
                debit: new DebitData(id: 987, amount: 25),
                links: new PayCodeLinksData(
                    redeem: 'https://example.test/r/PC-COCKPIT-IDEM',
                    redeem_path: '/r/PC-COCKPIT-IDEM',
                ),
                allocations: [
                    ['internal' => 'must-not-leak'],
                ],
            );
        }
    };

    app()->instance(GeneratePayCode::class, $fakeGeneratePayCode);

    actingAsTestUser();

    $payload = [
        'cash' => [
            'amount' => 25,
            'currency' => 'PHP',
        ],
        'inputs' => [
            'fields' => [],
        ],
        'feedback' => [
            'mobile' => null,
        ],
        'rider' => [
            'message' => null,
        ],
    ];

    $headers = [
        'Accept' => 'application/json',
        'Idempotency-Key' => 'cockpit-idem-1',
        'X-Correlation-ID' => 'correlation-cockpit-idem-1',
    ];

    $first = $this->withHeaders($headers)
        ->post(route('x-change.cockpit.quick-generate.store'), $payload)
        ->assertCreated()
        ->assertJsonPath('status', 'issued')
        ->assertJsonPath('idempotency.status', 'replay-safe')
        ->assertJsonPath('idempotency.key', 'cockpit-idem-1')
        ->assertJsonPath('idempotency.persisted', true)
        ->assertJsonPath('idempotency.fingerprinted', true)
        ->assertJsonPath('idempotency.replay_checked', true)
        ->assertJsonPath('idempotency.replayed', false)
        ->assertJsonPath('result.code', 'PC-COCKPIT-IDEM')
        ->assertJsonMissingPath('result.voucher_id')
        ->assertJsonMissingPath('result.wallet');

    $second = $this->withHeaders($headers)
        ->post(route('x-change.cockpit.quick-generate.store'), $payload)
        ->assertOk()
        ->assertJsonPath('status', 'replayed')
        ->assertJsonPath('idempotency.status', 'replay-safe')
        ->assertJsonPath('idempotency.key', 'cockpit-idem-1')
        ->assertJsonPath('idempotency.persisted', true)
        ->assertJsonPath('idempotency.fingerprinted', true)
        ->assertJsonPath('idempotency.replay_checked', true)
        ->assertJsonPath('idempotency.replayed', true)
        ->assertJsonPath('result.code', 'PC-COCKPIT-IDEM')
        ->assertJsonMissingPath('result.voucher_id')
        ->assertJsonMissingPath('result.wallet')
        ->assertJsonMissing(['must-not-leak']);

    expect($fakeGeneratePayCode->calls)->toBe(1)
        ->and($second->json('result'))->toBe($first->json('result'));
});

it('returns conflict for quick generate idempotency key reuse with a different payload', function () {
    $fakeGeneratePayCode = new class extends GeneratePayCode
    {
        public int $calls = 0;

        public function __construct() {}

        /**
         * @param  array<string, mixed>  $input
         */
        public function handle(array $input): GeneratePayCodeResultData
        {
            $this->calls++;

            return new GeneratePayCodeResultData(
                voucher_id: 12345,
                code: 'PC-COCKPIT-CONFLICT',
                amount: $input['cash']['amount'],
                currency: $input['cash']['currency'],
                issuer: new IssuerData(id: data_get($input, 'metadata.issuer_id')),
                cost: new PricingEstimateData,
                wallet: [],
                debit: new DebitData,
                links: new PayCodeLinksData(
                    redeem: 'https://example.test/r/PC-COCKPIT-CONFLICT',
                    redeem_path: '/r/PC-COCKPIT-CONFLICT',
                ),
            );
        }
    };

    app()->instance(GeneratePayCode::class, $fakeGeneratePayCode);

    actingAsTestUser();

    $payload = [
        'cash' => [
            'amount' => 25,
            'currency' => 'PHP',
        ],
        'inputs' => [
            'fields' => [],
        ],
        'feedback' => [
            'mobile' => null,
        ],
        'rider' => [
            'message' => null,
        ],
    ];

    $headers = [
        'Accept' => 'application/json',
        'Idempotency-Key' => 'cockpit-idem-conflict-1',
    ];

    $this->withHeaders($headers)
        ->post(route('x-change.cockpit.quick-generate.store'), $payload)
        ->assertCreated();

    data_set($payload, 'cash.amount', 26);

    $this->withHeaders($headers)
        ->post(route('x-change.cockpit.quick-generate.store'), $payload)
        ->assertConflict()
        ->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');

    expect($fakeGeneratePayCode->calls)->toBe(1);
});

it('validates quick generate mutation requests with the existing issuance request contract', function () {
    $fakeGeneratePayCode = new class extends GeneratePayCode
    {
        public bool $called = false;

        public function __construct() {}

        /**
         * @param  array<string, mixed>  $input
         */
        public function handle(array $input): GeneratePayCodeResultData
        {
            $this->called = true;

            return new GeneratePayCodeResultData(
                voucher_id: 1,
                code: 'PC-SHOULD-NOT-RUN',
                amount: 1,
                currency: 'PHP',
                issuer: new IssuerData(id: 1),
                cost: new PricingEstimateData,
                wallet: [],
                debit: new DebitData,
                links: new PayCodeLinksData(redeem: '#', redeem_path: '#'),
            );
        }
    };

    app()->instance(GeneratePayCode::class, $fakeGeneratePayCode);

    actingAsTestUser();

    $this->withHeader('Accept', 'application/json')
        ->post(route('x-change.cockpit.quick-generate.store'), [
            'inputs' => [
                'fields' => [],
            ],
            'feedback' => [],
            'rider' => [],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cash']);

    expect($fakeGeneratePayCode->called)->toBeFalse();
});

it('keeps the public pay code generation api route owned by the existing controller', function () {
    $route = Route::getRoutes()->getByName('x-change.api.pay-codes.generate');

    expect($route)->not->toBeNull()
        ->and($route->getActionName())->toBe(GeneratePayCodeController::class);
});

it('requires authentication for cockpit routes', function (string $route, array $parameters) {
    $this->withHeader('Accept', 'application/json')
        ->get(route($route, $parameters))
        ->assertUnauthorized();
})->with([
    'dashboard' => ['x-change.cockpit.dashboard', []],
    'accounts' => ['x-change.cockpit.accounts.index', []],
    'funding' => ['x-change.cockpit.funding.index', []],
    'quick generate' => ['x-change.cockpit.quick-generate', []],
    'pay code explorer' => ['x-change.cockpit.pay-codes.index', []],
    'voucher detail' => ['x-change.cockpit.pay-codes.show', ['code' => 'PC-READY-001']],
    'distribution workspace' => ['x-change.cockpit.pay-codes.distribution', ['code' => 'PC-READY-001']],
]);

it('registers only the guarded issuance, funding, payout recovery, and Account Cockpit mutation routes', function () {
    $mutatingRoutes = collect(Route::getRoutes())
        ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'x-change.cockpit.'))
        ->filter(fn ($route): bool => collect($route->methods())
            ->intersect(['POST', 'PUT', 'PATCH', 'DELETE'])
            ->isNotEmpty()
        );

    expect($mutatingRoutes->pluck('action.as')->values()->all())->toBe([
        'x-change.cockpit.api-partners.clients.store',
        'x-change.cockpit.api-partners.clients.checks.store',
        'x-change.cockpit.api-partners.clients.suspensions.store',
        'x-change.cockpit.api-partners.clients.revocations.store',
        'x-change.cockpit.commercial.offerings.store',
        'x-change.cockpit.commercial.offerings.approvals.store',
        'x-change.cockpit.commercial.offerings.activations.store',
        'x-change.cockpit.commercial.partners.store',
        'x-change.cockpit.commercial.partner_revisions.approvals.store',
        'x-change.cockpit.commercial.partners.destinations.store',
        'x-change.cockpit.commercial.partner_destination_revisions.approvals.store',
        'x-change.cockpit.commercial.provider_cost_batches.store',
        'x-change.cockpit.commercial.commission_payout_batches.store',
        'x-change.cockpit.commercial.commission_payout_batches.approvals.store',
        'x-change.cockpit.commercial.commission_payout_batches.submissions.store',
        'x-change.cockpit.commercial.commission_payout_batches.reconciliations.store',
        'x-change.cockpit.commercial.commission_payout_batches.retries.store',
        'x-change.cockpit.campaigns.store',
        'x-change.cockpit.campaigns.intakes.store',
        'x-change.cockpit.campaigns.intakes.update',
        'x-change.cockpit.campaigns.intakes.convert',
        'x-change.cockpit.campaigns.intakes.destroy',
        'x-change.cockpit.campaigns.destroy',
        'x-change.cockpit.campaigns.rows.store',
        'x-change.cockpit.campaigns.voucher-blueprint.update',
        'x-change.cockpit.campaigns.authorizations.store',
        'x-change.cockpit.campaigns.authorizations.deliveries.store',
        'x-change.cockpit.campaigns.fulfillments.pay-codes.store',
        'x-change.cockpit.campaigns.deliveries.store',
        'x-change.cockpit.campaigns.deliveries.retries.store',
        'x-change.cockpit.campaigns.deliveries.resends.store',
        'x-change.cockpit.campaigns.fulfillments.bank-transfers.store',
        'x-change.cockpit.campaigns.fulfillments.bank-transfers.reconciliations.store',
        'x-change.cockpit.campaigns.fulfillments.fallbacks.store',
        'x-change.cockpit.campaigns.imports.store',
        'x-change.cockpit.campaigns.imports.apply',
        'x-change.cockpit.campaigns.imports.mapping.update',
        'x-change.cockpit.campaigns.imports.destroy',
        'x-change.cockpit.accounts.scenarios.funding-destinations.store',
        'x-change.cockpit.accounts.providers.funding-destination.update',
        'x-change.cockpit.accounts.funding-qr-merchant-profile.update',
        'x-change.cockpit.funding.liquidity-refreshes.store',
        'x-change.cockpit.funding.requests.store',
        'x-change.cockpit.funding.requests.transfer-checks.store',
        'x-change.cockpit.funding.requests.reviews.store',
        'x-change.cockpit.funding.requests.approvals.store',
        'x-change.cockpit.funding.requests.pay-code-claims.store',
        'x-change.cockpit.funding.pay-code-inspections.store',
        'x-change.cockpit.funding.pay-code-claims.store',
        'x-change.cockpit.funding.intents.store',
        'x-change.cockpit.funding.intents.verification-checks.store',
        'x-change.cockpit.funding.standing-addresses.netbank.store',
        'x-change.cockpit.funding.standing-addresses.netbank.history-checks.store',
        'x-change.cockpit.funding.standing-addresses.netbank.receipts.approve',
        'x-change.cockpit.funding.scenarios.qrph.store',
        'x-change.cockpit.funding.suspense.reconciliation-requests.store',
        'x-change.cockpit.funding.reconciliations.approve',
        'x-change.cockpit.quick-generate.store',
        'x-change.cockpit.quick-generate.claim-previews.store',
        'x-change.cockpit.quick-generate.artwork-previews.store',
        'x-change.cockpit.pay-code-templates.store',
        'x-change.cockpit.pay-code-templates.update',
        'x-change.cockpit.rider-library.store',
        'x-change.cockpit.rider-library.pin',
        'x-change.cockpit.rider-library.forget',
        'x-change.cockpit.pay-codes.payout-corrections.store',
        'x-change.cockpit.pay-codes.terminal-actions.store',
    ])->and(Route::getRoutes()->getByName('x-change.cockpit.funding.intents.store')?->getActionName())
        ->toBe(CockpitFundingIntentController::class)
        ->and(Route::getRoutes()->getByName('x-change.cockpit.funding.scenarios.qrph.store')?->getActionName())
        ->toBe(CockpitQrPhFundingSimulationController::class);
});

it('documents the quick generate issuance boundary before mutation wiring', function () {
    $report = file_get_contents(__DIR__.'/../../../docs/ui-cockpit/reports/004-quick-generate-issuance-boundary-plan.md');

    expect($report)->toBeString()
        ->and($report)->toContain('Cockpit Slice 17')
        ->and($report)->toContain('Existing issuance owner')
        ->and($report)->toContain('GeneratePayCode')
        ->and($report)->toContain('GeneratePayCodeController')
        ->and($report)->toContain('Authorization')
        ->and($report)->toContain('Pricing')
        ->and($report)->toContain('Funding')
        ->and($report)->toContain('Idempotency')
        ->and($report)->toContain('Redaction')
        ->and($report)->toContain('No Cockpit mutation route is registered in Slice 17');
});

it('documents the quick generate request draft contract before persistence or mutation wiring', function () {
    $report = file_get_contents(__DIR__.'/../../../docs/ui-cockpit/reports/005-quick-generate-request-draft-contract-baseline.md');

    expect($report)->toBeString()
        ->and($report)->toContain('Cockpit Slice 18')
        ->and($report)->toContain('x-change.cockpit.quick-generate-draft.v1')
        ->and($report)->toContain('template_key')
        ->and($report)->toContain('amount')
        ->and($report)->toContain('currency')
        ->and($report)->toContain('recipient_reference')
        ->and($report)->toContain('purpose')
        ->and($report)->toContain('idempotency_key')
        ->and($report)->toContain('Drafts are local and read-only in Slice 18')
        ->and($report)->toContain('No draft persistence or mutation route is registered in Slice 18');
});

it('documents the quick generate authorization gates before mutation wiring', function () {
    $report = file_get_contents(__DIR__.'/../../../docs/ui-cockpit/reports/006-quick-generate-authorization-gate-baseline.md');

    expect($report)->toBeString()
        ->and($report)->toContain('Cockpit Slice 19')
        ->and($report)->toContain('operator-authenticated')
        ->and($report)->toContain('can-view-cockpit')
        ->and($report)->toContain('can-generate-pay-code')
        ->and($report)->toContain('can-call-providers')
        ->and($report)->toContain('can-move-money')
        ->and($report)->toContain('Authorization gates are read-only facts in Slice 19')
        ->and($report)->toContain('No authorization gate enables generation in Slice 19');
});

it('documents the quick generate pricing gates before calculation or reservation wiring', function () {
    $report = file_get_contents(__DIR__.'/../../../docs/ui-cockpit/reports/007-quick-generate-pricing-gate-baseline.md');

    expect($report)->toBeString()
        ->and($report)->toContain('Cockpit Slice 20')
        ->and($report)->toContain('template-selected')
        ->and($report)->toContain('amount-input-present')
        ->and($report)->toContain('pricing-service-wired')
        ->and($report)->toContain('funding-source-selected')
        ->and($report)->toContain('funds-reservation')
        ->and($report)->toContain('provider-fee-quote')
        ->and($report)->toContain('Pricing gates are read-only facts in Slice 20')
        ->and($report)->toContain('No pricing gate calculates prices, reserves funds, or calls providers in Slice 20');
});

it('documents the quick generate funding gates before wallet access or reservation wiring', function () {
    $report = file_get_contents(__DIR__.'/../../../docs/ui-cockpit/reports/008-quick-generate-funding-gate-baseline.md');

    expect($report)->toBeString()
        ->and($report)->toContain('Cockpit Slice 21')
        ->and($report)->toContain('funding-policy-known')
        ->and($report)->toContain('issuer-wallet-identified')
        ->and($report)->toContain('wallet-balance-available')
        ->and($report)->toContain('sufficient-funds')
        ->and($report)->toContain('funds-reservation-ready')
        ->and($report)->toContain('provider-funding-ready')
        ->and($report)->toContain('Funding gates are read-only facts in Slice 21')
        ->and($report)->toContain('No funding gate reads wallets, reserves funds, debits balances, or calls providers in Slice 21');
});

it('documents the quick generate idempotency gates before persistence or mutation wiring', function () {
    $report = file_get_contents(__DIR__.'/../../../docs/ui-cockpit/reports/009-quick-generate-idempotency-gate-baseline.md');

    expect($report)->toBeString()
        ->and($report)->toContain('Cockpit Slice 22')
        ->and($report)->toContain('idempotency-policy-known')
        ->and($report)->toContain('idempotency-key-source-defined')
        ->and($report)->toContain('payload-fingerprint-defined')
        ->and($report)->toContain('replay-lookup-ready')
        ->and($report)->toContain('conflict-response-ready')
        ->and($report)->toContain('ttl-policy-ready')
        ->and($report)->toContain('Idempotency gates are read-only facts in Slice 22')
        ->and($report)->toContain('No idempotency gate persists keys, fingerprints payloads, reads replay records, or enables mutation routes in Slice 22');
});

it('documents the quick generate validation and redaction gates before request handling or mutation wiring', function () {
    $report = file_get_contents(__DIR__.'/../../../docs/ui-cockpit/reports/010-quick-generate-validation-redaction-gate-baseline.md');

    expect($report)->toBeString()
        ->and($report)->toContain('Cockpit Slice 23')
        ->and($report)->toContain('request-schema-known')
        ->and($report)->toContain('required-fields-defined')
        ->and($report)->toContain('validation-rules-wired')
        ->and($report)->toContain('sensitive-fields-redacted')
        ->and($report)->toContain('sanitized-preview-ready')
        ->and($report)->toContain('validation-error-contract-ready')
        ->and($report)->toContain('Validation and redaction gates are read-only facts in Slice 23')
        ->and($report)->toContain('No validation/redaction gate validates requests, persists payloads, exposes submitted PII, or enables mutation routes in Slice 23');
});

it('documents the quick generate mutation handoff boundary before mutation wiring', function () {
    $report = file_get_contents(__DIR__.'/../../../docs/ui-cockpit/reports/011-quick-generate-mutation-handoff-boundary-plan.md');

    expect($report)->toBeString()
        ->and($report)->toContain('Cockpit Slice 24')
        ->and($report)->toContain('existing-issuance-owner-identified')
        ->and($report)->toContain('generate-pay-code-action-handoff')
        ->and($report)->toContain('generate-pay-code-controller-handoff')
        ->and($report)->toContain('preconditions-green')
        ->and($report)->toContain('side-effect-boundary-confirmed')
        ->and($report)->toContain('operator-response-contract-ready')
        ->and($report)->toContain('Mutation handoff remains a read-only boundary plan in Slice 24')
        ->and($report)->toContain('No Cockpit mutation route calls GeneratePayCode, GeneratePayCodeController, providers, wallets, journal, action, or feedback in Slice 24');
});

it('documents the quick generate mutation preconditions review before mutation approval', function () {
    $report = file_get_contents(__DIR__.'/../../../docs/ui-cockpit/reports/012-quick-generate-mutation-preconditions-review.md');

    expect($report)->toBeString()
        ->and($report)->toContain('Cockpit Slice 25')
        ->and($report)->toContain('authorization-ready')
        ->and($report)->toContain('pricing-ready')
        ->and($report)->toContain('funding-ready')
        ->and($report)->toContain('idempotency-ready')
        ->and($report)->toContain('validation-redaction-ready')
        ->and($report)->toContain('handoff-ready')
        ->and($report)->toContain('operator-response-ready')
        ->and($report)->toContain('Mutation preconditions remain blocked in Slice 25')
        ->and($report)->toContain('No Cockpit mutation route, mutation approval, request validation execution, voucher issuance, provider call, wallet access, journal write, action run, or feedback delivery is introduced in Slice 25');
});

it('documents the quick generate mutation authorization decision point before mutation route scaffolding', function () {
    $report = file_get_contents(__DIR__.'/../../../docs/ui-cockpit/reports/013-quick-generate-mutation-authorization-decision-point.md');

    expect($report)->toBeString()
        ->and($report)->toContain('Cockpit Slice 26')
        ->and($report)->toContain('Mutation Authorization Decision Point')
        ->and($report)->toContain('not_authorized')
        ->and($report)->toContain('human-approval-required-before-route-scaffold')
        ->and($report)->toContain('request-explicit-approval-or-continue-read-only-hardening')
        ->and($report)->toContain('No Cockpit mutation route is authorized in Slice 26')
        ->and($report)->toContain('No mutation endpoints, voucher issuance, request validation execution, payload persistence, provider call, wallet access, journal write, action run, feedback delivery, campaign behavior, or money movement is introduced in Slice 26');
});

it('documents the cross package read model integration baseline before mutations', function () {
    $report = file_get_contents(__DIR__.'/../../../docs/ui-cockpit/reports/014-cross-package-read-model-integration-baseline.md');

    expect($report)->toBeString()
        ->and($report)->toContain('Cockpit Slice 27')
        ->and($report)->toContain('x-journal evidence summaries')
        ->and($report)->toContain('x-action safe CTA/action summaries')
        ->and($report)->toContain('x-feedback communication delivery summaries')
        ->and($report)->toContain('not hard Composer dependencies')
        ->and($report)->toContain('do not write journal entries')
        ->and($report)->toContain('do not execute or authorize actions')
        ->and($report)->toContain('do not send, retry, or call providers');
});
