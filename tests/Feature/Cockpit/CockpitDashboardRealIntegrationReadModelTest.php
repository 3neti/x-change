<?php

declare(strict_types=1);

it('hydrates dashboard connected services from installed read-only integration packages', function () {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Dashboard')
        ->assertJsonPath('props.read_model.code', null)
        ->assertJsonPath('props.read_model.voucher.status', 'not_wired')
        ->assertJsonPath('props.read_model.execution.status', 'not_wired')
        ->assertJsonPath('props.read_model.journal.status', 'available')
        ->assertJsonPath('props.read_model.journal.authorized', true)
        ->assertJsonPath('props.read_model.journal.redactions.source', 'x-journal')
        ->assertJsonPath('props.read_model.journal.redactions.evidence_only', true)
        ->assertJsonPath('props.read_model.journal.redactions.writes_journal_entries', false)
        ->assertJsonPath('props.read_model.actions.status', 'available')
        ->assertJsonPath('props.read_model.actions.authorized', true)
        ->assertJsonPath('props.read_model.actions.redactions.source', 'x-action')
        ->assertJsonPath('props.read_model.actions.redactions.presentation_only', true)
        ->assertJsonPath('props.read_model.actions.redactions.executes_action', false)
        ->assertJsonPath('props.read_model.feedback.status', 'available')
        ->assertJsonPath('props.read_model.feedback.authorized', true)
        ->assertJsonPath('props.read_model.feedback.redactions.source', 'x-feedback')
        ->assertJsonPath('props.read_model.feedback.redactions.communication_state_only', true)
        ->assertJsonPath('props.read_model.feedback.redactions.sends_feedback', false)
        ->assertJsonMissingPath('props.read_model.provider_payload')
        ->assertJsonMissingPath('props.read_model.raw_payload')
        ->assertJsonMissingPath('props.read_model.wallet');
});

it('hydrates dashboard campaign package presence from installed x-campaign without selected campaign context', function () {
    actingAsTestUser();

    $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.dashboard'))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/Dashboard')
        ->assertJsonPath('props.campaign_read_model.status', 'available')
        ->assertJsonPath('props.campaign_read_model.authorized', true)
        ->assertJsonPath('props.campaign_read_model.source', 'x-campaign')
        ->assertJsonPath('props.campaign_read_model.facts.context_status', 'no-campaign-selected')
        ->assertJsonPath('props.campaign_read_model.facts.selected', false)
        ->assertJsonPath('props.campaign_read_model.facts.metadata.package_available', true)
        ->assertJsonPath('props.campaign_read_model.redactions.payloads', 'campaign-cockpit-package-presence-only')
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
        ->assertJsonMissingPath('props.campaign_read_model.delivery_dispatch_payload');
});
