<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('registers read-only cockpit routes under the x cockpit namespace', function () {
    expect(route('x-change.cockpit.dashboard'))->toBe('http://localhost/x/cockpit')
        ->and(route('x-change.cockpit.quick-generate'))->toBe('http://localhost/x/cockpit/quick-generate')
        ->and(route('x-change.cockpit.pay-codes.index'))->toBe('http://localhost/x/cockpit/pay-codes')
        ->and(route('x-change.cockpit.pay-codes.show', ['code' => 'PC-READY-001']))->toBe('http://localhost/x/cockpit/pay-codes/PC-READY-001')
        ->and(route('x-change.cockpit.pay-codes.distribution', ['code' => 'PC-READY-001']))->toBe('http://localhost/x/cockpit/pay-codes/PC-READY-001/distribution');
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
        ->assertJsonPath('props.redaction.payloads', 'redacted-until-authorized-read-models-exist');
})->with([
    'dashboard' => ['x-change.cockpit.dashboard', [], 'x-change/cockpit/Dashboard'],
    'quick generate' => ['x-change.cockpit.quick-generate', [], 'x-change/cockpit/QuickGenerate'],
    'pay code explorer' => ['x-change.cockpit.pay-codes.index', [], 'x-change/cockpit/PayCodeExplorer'],
    'voucher detail' => ['x-change.cockpit.pay-codes.show', ['code' => 'PC-READY-001'], 'x-change/cockpit/VoucherDetail'],
    'distribution workspace' => ['x-change.cockpit.pay-codes.distribution', ['code' => 'PC-READY-001'], 'x-change/cockpit/DistributionWorkspace'],
]);

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
        ->assertJsonMissingPath('props.pay_codes_read_model.provider_payload')
        ->assertJsonMissingPath('props.pay_codes_read_model.raw_payload')
        ->assertJsonMissingPath('props.pay_codes_read_model.wallet')
        ->assertJsonMissingPath('props.pay_codes_read_model.provider');
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
        ->assertJsonPath('props.quick_generate_read_model.draft_contract.schema', 'x-change.cockpit.quick-generate-draft.v1')
        ->assertJsonPath('props.quick_generate_read_model.draft_contract.status', 'draft_only')
        ->assertJsonPath('props.quick_generate_read_model.draft_contract.template_key', 'money-changer')
        ->assertJsonPath('props.quick_generate_read_model.draft_contract.amount', null)
        ->assertJsonPath('props.quick_generate_read_model.draft_contract.currency', 'PHP')
        ->assertJsonPath('props.quick_generate_read_model.draft_contract.recipient_reference', null)
        ->assertJsonPath('props.quick_generate_read_model.draft_contract.idempotency_key', null)
        ->assertJsonPath('props.quick_generate_read_model.draft_contract.redactions.payloads', 'draft-shape-only')
        ->assertJsonPath('props.quick_generate_read_model.authorization.status', 'blocked')
        ->assertJsonPath('props.quick_generate_read_model.authorization.gates.0.key', 'operator-authenticated')
        ->assertJsonPath('props.quick_generate_read_model.authorization.gates.0.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.authorization.gates.2.key', 'can-generate-pay-code')
        ->assertJsonPath('props.quick_generate_read_model.authorization.gates.2.status', 'blocked')
        ->assertJsonPath('props.quick_generate_read_model.authorization.gates.3.key', 'can-call-providers')
        ->assertJsonPath('props.quick_generate_read_model.authorization.gates.4.key', 'can-move-money')
        ->assertJsonPath('props.quick_generate_read_model.authorization.redactions.payloads', 'authorization-gates-only')
        ->assertJsonPath('props.quick_generate_read_model.action.enabled', false)
        ->assertJsonPath('props.quick_generate_read_model.action.reason', 'issuance-not-wired')
        ->assertJsonPath('props.quick_generate_read_model.redactions.payloads', 'sanitized-quick-generate-catalog-only')
        ->assertJsonMissingPath('props.quick_generate_read_model.provider_payload')
        ->assertJsonMissingPath('props.quick_generate_read_model.raw_payload')
        ->assertJsonMissingPath('props.quick_generate_read_model.wallet')
        ->assertJsonMissingPath('props.quick_generate_read_model.balance')
        ->assertJsonMissingPath('props.quick_generate_read_model.funding_source');
});

it('requires authentication for cockpit routes', function (string $route, array $parameters) {
    $this->withHeader('Accept', 'application/json')
        ->get(route($route, $parameters))
        ->assertUnauthorized();
})->with([
    'dashboard' => ['x-change.cockpit.dashboard', []],
    'quick generate' => ['x-change.cockpit.quick-generate', []],
    'pay code explorer' => ['x-change.cockpit.pay-codes.index', []],
    'voucher detail' => ['x-change.cockpit.pay-codes.show', ['code' => 'PC-READY-001']],
    'distribution workspace' => ['x-change.cockpit.pay-codes.distribution', ['code' => 'PC-READY-001']],
]);

it('does not register cockpit mutation routes', function () {
    $mutatingRoutes = collect(Route::getRoutes())
        ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'x-change.cockpit.'))
        ->filter(fn ($route): bool => collect($route->methods())
            ->intersect(['POST', 'PUT', 'PATCH', 'DELETE'])
            ->isNotEmpty()
        );

    expect($mutatingRoutes)->toHaveCount(0);
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
