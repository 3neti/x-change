<?php

declare(strict_types=1);

use LBHurtado\XJournal\Models\ExecutionJournalEntry;

it('hydrates distribution workspace with real x-journal read-only audit guidance', function () {
    actingAsTestUser();

    $voucher = issueVoucher(validVoucherInstructions(150.00));

    ExecutionJournalEntry::query()->create([
        'reference_number' => 'ERN-COCKPIT-DISTRIBUTION-JOURNAL-001',
        'event_type' => 'distribution.audit.recorded',
        'occurred_at' => now(),
        'actor_type' => 'operator',
        'actor_id' => 'operator-cockpit-distribution-journal',
        'subject_type' => 'voucher',
        'subject_id' => $voucher->code,
        'correlation_id' => 'corr-cockpit-distribution-journal',
        'causation_id' => 'cause-cockpit-distribution-journal',
        'execution_id' => null,
        'actor' => [
            'type' => 'operator',
            'id' => 'operator-cockpit-distribution-journal',
        ],
        'subject' => [
            'type' => 'voucher',
            'id' => $voucher->code,
        ],
        'money' => [
            'amount' => '150.00',
            'currency' => 'PHP',
        ],
        'references' => [
            'code' => $voucher->code,
            'correlation_id' => 'corr-cockpit-distribution-journal',
        ],
        'payload' => [
            'summary' => 'Distribution audit guidance from x-journal.',
            'raw_payload' => ['secret' => 'must-not-render'],
            'provider_payload' => ['token' => 'must-not-render'],
        ],
        'integrity' => [
            'hash' => 'hash-cockpit-distribution-journal',
        ],
        'metadata' => [
            'source' => 'x-journal-test-fixture',
            'wallet' => ['balance' => 'must-not-render'],
        ],
    ]);

    $response = $this->withHeader('X-Inertia', 'true')
        ->get(route('x-change.cockpit.pay-codes.distribution', ['code' => $voucher->code]))
        ->assertOk()
        ->assertJsonPath('component', 'x-change/cockpit/DistributionWorkspace')
        ->assertJsonPath('props.distribution_workspace_read_model.code', $voucher->code)
        ->assertJsonPath('props.distribution_workspace_read_model.analytics.0.key', 'ERN-COCKPIT-DISTRIBUTION-JOURNAL-001')
        ->assertJsonPath('props.distribution_workspace_read_model.analytics.0.label', 'Journal: distribution.audit.recorded')
        ->assertJsonPath('props.distribution_workspace_read_model.analytics.0.status', 'available')
        ->assertJsonPath('props.distribution_workspace_read_model.analytics.0.source', 'x-journal')
        ->assertJsonPath('props.distribution_workspace_read_model.analytics.0.read_only', true)
        ->assertJsonPath('props.distribution_workspace_read_model.analytics.0.available', true)
        ->assertJsonPath('props.distribution_workspace_read_model.analytics.0.metadata.event_type', 'distribution.audit.recorded')
        ->assertJsonPath('props.distribution_workspace_read_model.analytics.0.metadata.payload_policy', 'journal-evidence-summary-only')
        ->assertJsonPath('props.distribution_workspace_read_model.analytics.0.metadata.evidence_only', true)
        ->assertJsonPath('props.distribution_workspace_read_model.analytics.0.metadata.writes_journal', false)
        ->assertJsonPath('props.distribution_workspace_read_model.redactions.journal_writes_enabled', false)
        ->assertJsonPath('props.distribution_workspace_read_model.redactions.journal_payloads_exposed', false)
        ->assertJsonMissingPath('props.distribution_workspace_read_model.analytics.0.payload')
        ->assertJsonMissingPath('props.distribution_workspace_read_model.analytics.0.metadata.wallet');

    expect($response->getContent())
        ->toContain('Distribution audit guidance from x-journal.')
        ->not->toContain('must-not-render');
});
