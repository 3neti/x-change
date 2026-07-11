<?php

declare(strict_types=1);

it('publishes current quick generate runtime copy instead of stale baseline copy', function () {
    actingAsTestUser();

    $response = $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.quick-generate'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/QuickGenerate')
        ->assertJsonPath('props.quick_generate_read_model.status', 'available')
        ->assertJsonPath('props.quick_generate_read_model.authorized', true)
        ->assertJsonPath('props.quick_generate_read_model.runtime_inputs.0.key', 'amount')
        ->assertJsonPath('props.quick_generate_read_model.runtime_inputs.0.value', 'Use the Quick Generate form')
        ->assertJsonPath('props.quick_generate_read_model.runtime_inputs.0.helper', 'Pricing and funding preflights appear after a successful form submit.')
        ->assertJsonPath('props.quick_generate_read_model.runtime_inputs.1.value', 'Use the Quick Generate form')
        ->assertJsonPath('props.quick_generate_read_model.runtime_inputs.2.value', 'Optional form note')
        ->assertJsonPath('props.quick_generate_read_model.pricing_summaries.0.value', 'Shown after submit')
        ->assertJsonPath('props.quick_generate_read_model.pricing_summaries.1.value', 'Shown after submit')
        ->assertJsonPath('props.quick_generate_read_model.pricing_summaries.2.value', 'Existing handoff')
        ->assertJsonPath('props.quick_generate_read_model.pricing_gate.status', 'runtime-informational')
        ->assertJsonPath('props.quick_generate_read_model.pricing_gate.checks.1.key', 'amount-input-present')
        ->assertJsonPath('props.quick_generate_read_model.pricing_gate.checks.1.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.funding_gate.status', 'runtime-informational')
        ->assertJsonPath('props.quick_generate_read_model.validation_redaction_gate.status', 'backend-ready')
        ->assertJsonPath('props.quick_generate_read_model.mutation_preconditions_review.status', 'existing-handoff-ready')
        ->assertJsonPath('props.quick_generate_read_model.mutation_preconditions_review.recommendation', 'use-existing-issuance-handoff')
        ->assertJsonPath('props.quick_generate_read_model.mutation_authorization_decision.status', 'approved-handoff')
        ->assertJsonPath('props.quick_generate_read_model.mutation_authorization_decision.decision', 'authorized_existing_handoff')
        ->assertJsonPath('props.quick_generate_read_model.authorization.status', 'runtime-ready')
        ->assertJsonPath('props.quick_generate_read_model.authorization.gates.2.key', 'can-generate-pay-code')
        ->assertJsonPath('props.quick_generate_read_model.authorization.gates.2.status', 'passed')
        ->assertJsonPath('props.quick_generate_read_model.action.enabled', true)
        ->assertJsonPath('props.quick_generate_read_model.action.reason', 'existing-issuance-handoff-enabled');

    $payload = json_encode($response->json('props.quick_generate_read_model'), JSON_THROW_ON_ERROR);

    expect($payload)
        ->not->toContain('Pending operator input')
        ->not->toContain('Pending recipient selection')
        ->not->toContain('Pending purpose note')
        ->not->toContain('No pricing or funding calculation is executed in Slice 16')
        ->not->toContain('No operator amount input is accepted by Cockpit in Slice 20')
        ->not->toContain('No Cockpit mutation route is registered')
        ->not->toContain('not_authorized')
        ->not->toContain('remain-read-only');
});
