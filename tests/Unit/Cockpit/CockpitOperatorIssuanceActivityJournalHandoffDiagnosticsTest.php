<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityJournalHandoffResultData;
use LBHurtado\XChange\Services\Cockpit\CockpitOperatorIssuanceActivityJournalHandoffDiagnostics;

it('classifies recorded journal handoff evidence for operator display', function () {
    $diagnostic = app(CockpitOperatorIssuanceActivityJournalHandoffDiagnostics::class)
        ->classify(new CockpitOperatorIssuanceActivityJournalHandoffResultData(
            status: 'recorded',
            activity_id: 'activity-1',
            correlation_id: 'corr-1',
            journal_entry_id: 'journal-entry-1',
            writes_journal: true,
            source: 'x-journal',
            reason: 'Journal entry was recorded.',
        ));

    expect($diagnostic)->toMatchArray([
        'classification' => 'recorded',
        'tone' => 'success',
        'label' => 'Journal recorded',
        'operator_action' => 'none',
        'read_only' => true,
        'retry_enabled' => false,
        'mutation_enabled' => false,
        'raw_payloads_exposed' => false,
    ]);
});

it('classifies not wired journal handoff evidence as configuration pending', function () {
    $diagnostic = app(CockpitOperatorIssuanceActivityJournalHandoffDiagnostics::class)
        ->classify(new CockpitOperatorIssuanceActivityJournalHandoffResultData(
            status: 'not_wired',
            activity_id: 'activity-1',
            correlation_id: 'corr-1',
        ));

    expect($diagnostic)->toMatchArray([
        'classification' => 'not_wired',
        'tone' => 'neutral',
        'label' => 'Journal handoff not wired',
        'operator_action' => 'configure_when_ready',
        'read_only' => true,
        'retry_enabled' => false,
        'mutation_enabled' => false,
        'raw_payloads_exposed' => false,
    ]);
});

it('classifies failed non blocking journal handoff evidence for investigation without retry controls', function () {
    $diagnostic = app(CockpitOperatorIssuanceActivityJournalHandoffDiagnostics::class)
        ->classify(new CockpitOperatorIssuanceActivityJournalHandoffResultData(
            status: 'failed_non_blocking',
            activity_id: 'activity-1',
            correlation_id: 'corr-1',
            source: 'x-journal',
            reason: 'Journal handoff failed; issuance activity was still preserved.',
            metadata: [
                'exception' => 'Connection timeout',
                'token' => 'must-not-leak',
                'provider_payload' => 'must-not-leak',
            ],
        ));

    expect($diagnostic)->toMatchArray([
        'classification' => 'failed_non_blocking',
        'tone' => 'warning',
        'label' => 'Journal handoff failed non-blocking',
        'operator_action' => 'review_configuration',
        'read_only' => true,
        'retry_enabled' => false,
        'mutation_enabled' => false,
        'raw_payloads_exposed' => false,
    ])
        ->and($diagnostic)->not->toHaveKey('token')
        ->and($diagnostic)->not->toHaveKey('provider_payload');
});
