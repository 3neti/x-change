<?php

declare(strict_types=1);

it('hydrates quick generate with safe campaign context from query parameters', function (): void {
    actingAsTestUser();

    $this
        ->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.quick-generate', [
            'campaign_planning_key' => 'plan-35c',
            'campaign_execution_id' => 'exec-35c',
            'campaign_id' => 'campaign-35c',
            'campaign_audience_id' => 'audience-35c',
            'campaign_recipient_id' => 'recipient-35c',
            'campaign_source' => 'campaign_cockpit',
            'campaign_template_key' => 'ofw-remittance',
            'campaign_amount' => '500.00',
            'campaign_currency' => 'PHP',
            'campaign_recipient_reference' => '09173011987',
            'campaign_purpose' => 'Campaign payout',
        ]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/QuickGenerate')
        ->assertJsonPath('props.quick_generate_read_model.campaign_context.schema', 'x-change.cockpit.quick-generate-campaign-context.v1')
        ->assertJsonPath('props.quick_generate_read_model.campaign_context.status', 'available')
        ->assertJsonPath('props.quick_generate_read_model.campaign_context.authorized', true)
        ->assertJsonPath('props.quick_generate_read_model.campaign_context.read_only', true)
        ->assertJsonPath('props.quick_generate_read_model.campaign_context.mutates_campaign', false)
        ->assertJsonPath('props.quick_generate_read_model.campaign_context.planning_key', 'plan-35c')
        ->assertJsonPath('props.quick_generate_read_model.campaign_context.execution_id', 'exec-35c')
        ->assertJsonPath('props.quick_generate_read_model.campaign_context.campaign_id', 'campaign-35c')
        ->assertJsonPath('props.quick_generate_read_model.campaign_context.audience_id', 'audience-35c')
        ->assertJsonPath('props.quick_generate_read_model.campaign_context.recipient_id', 'recipient-35c')
        ->assertJsonPath('props.quick_generate_read_model.campaign_context.source', 'campaign_cockpit')
        ->assertJsonPath('props.quick_generate_read_model.campaign_context.draft.template_key', 'ofw-remittance')
        ->assertJsonPath('props.quick_generate_read_model.campaign_context.draft.amount', '500.00')
        ->assertJsonPath('props.quick_generate_read_model.campaign_context.draft.currency', 'PHP')
        ->assertJsonPath('props.quick_generate_read_model.campaign_context.draft.recipient_reference', '09173011987')
        ->assertJsonPath('props.quick_generate_read_model.campaign_context.draft.purpose', 'Campaign payout')
        ->assertJsonPath('props.quick_generate_read_model.campaign_context.draft.campaign.planning_key', 'plan-35c')
        ->assertJsonPath('props.quick_generate_read_model.campaign_context.draft.campaign.execution_id', 'exec-35c')
        ->assertJsonPath('props.quick_generate_read_model.campaign_context.redactions.payloads', 'campaign-context-prefill-only')
        ->assertJsonMissingPath('props.quick_generate_read_model.campaign_context.campaign_payload')
        ->assertJsonMissingPath('props.quick_generate_read_model.campaign_context.recipient_payload')
        ->assertJsonMissingPath('props.quick_generate_read_model.campaign_context.provider_payload')
        ->assertJsonMissingPath('props.quick_generate_read_model.campaign_context.wallet')
        ->assertJsonMissingPath('props.quick_generate_read_model.campaign_context.raw_payload');
});
