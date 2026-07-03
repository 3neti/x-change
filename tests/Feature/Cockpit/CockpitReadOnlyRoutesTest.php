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
