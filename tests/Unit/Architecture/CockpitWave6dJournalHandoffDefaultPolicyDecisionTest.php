<?php

declare(strict_types=1);

it('documents cockpit mutation wave 6d journal handoff default policy decision', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/114-wave-6d-journal-handoff-default-policy-decision.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 6D — Journal Handoff Default Policy Decision')
        ->and($report)->toContain('Status: Scaffolded / Decision recorded')
        ->and($report)->toContain('Decide that journal handoff must remain opt-in until idempotency, authorization, and failure semantics are hardened.')
        ->and($report)->toContain('Durable activity recording remains disabled by default.')
        ->and($report)->toContain('6E — Action / Feedback Handoff Default Policy Decision')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 6D — Journal Handoff Default Policy Decision')
        ->and($cockpitCompass)->toContain('reports/114-wave-6d-journal-handoff-default-policy-decision.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 6D — Journal Handoff Default Policy Decision')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/114-wave-6d-journal-handoff-default-policy-decision.md');
});
