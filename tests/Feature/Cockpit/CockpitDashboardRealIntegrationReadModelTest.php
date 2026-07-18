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
