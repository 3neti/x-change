<?php

declare(strict_types=1);

it('hydrates a safe campaign quick generate source link on the dashboard read model', function (): void {
    actingAsTestUser();

    $response = $this
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard', [
            'campaign_planning_key' => 'plan-37b',
            'campaign_execution_id' => 'exec-37b',
            'campaign_id' => 'campaign-37b',
            'campaign_audience_id' => 'audience-37b',
            'campaign_recipient_id' => 'recipient-37b',
            'campaign_source' => 'campaign_cockpit',
            'campaign_template_key' => 'ofw-remittance',
            'campaign_amount' => '500.00',
            'campaign_currency' => 'PHP',
            'campaign_recipient_reference' => '09173011987',
            'campaign_purpose' => 'Campaign payout',
        ]));

    $response
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Dashboard')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.schema', 'x-change.cockpit.campaign-quick-generate-link.v1')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.status', 'available')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.enabled', true)
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.read_only', true)
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.mutates_campaign', false)
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.route', 'x-change.cockpit.quick-generate')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.label', 'Open Quick Generate')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.planning_key', 'plan-37b')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.execution_id', 'exec-37b')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.campaign_id', 'campaign-37b')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.audience_id', 'audience-37b')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.recipient_id', 'recipient-37b')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.source', 'campaign_cockpit')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.draft.template_key', 'ofw-remittance')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.draft.amount', '500.00')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.draft.currency', 'PHP')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.draft.recipient_reference', '09173011987')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.draft.purpose', 'Campaign payout')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.redactions.payloads', 'campaign-source-link-query-only')
        ->assertJsonMissingPath('props.campaign_read_model.quick_generate_link.raw_payload')
        ->assertJsonMissingPath('props.campaign_read_model.quick_generate_link.provider_payload')
        ->assertJsonMissingPath('props.campaign_read_model.quick_generate_link.wallet')
        ->assertJsonMissingPath('props.campaign_read_model.quick_generate_link.pay_code_generation_payload')
        ->assertJsonMissingPath('props.campaign_read_model.quick_generate_link.delivery_dispatch_payload')
        ->assertJsonMissingPath('props.campaign_read_model.quick_generate_link.mutation_route');

    $href = $response->json('props.campaign_read_model.quick_generate_link.href');

    expect($href)
        ->toBeString()
        ->toContain('/x/cockpit/quick-generate')
        ->toContain('campaign_planning_key=plan-37b')
        ->toContain('campaign_execution_id=exec-37b')
        ->toContain('campaign_id=campaign-37b')
        ->toContain('campaign_audience_id=audience-37b')
        ->toContain('campaign_recipient_id=recipient-37b')
        ->toContain('campaign_source=campaign_cockpit')
        ->toContain('campaign_template_key=ofw-remittance')
        ->toContain('campaign_amount=500.00')
        ->toContain('campaign_currency=PHP')
        ->toContain('campaign_recipient_reference=09173011987')
        ->toContain('campaign_purpose=Campaign%20payout');
});

it('keeps the campaign quick generate source link unavailable without campaign context', function (): void {
    actingAsTestUser();

    $this
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard'))
        ->assertOk()
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.schema', 'x-change.cockpit.campaign-quick-generate-link.v1')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.status', 'not_available')
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.enabled', false)
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.href', null)
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.read_only', true)
        ->assertJsonPath('props.campaign_read_model.quick_generate_link.mutates_campaign', false);
});
