<?php

declare(strict_types=1);

it('documents cockpit mutation wave 7d journal handoff hardening baseline', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/124-wave-7d-journal-handoff-hardening-baseline.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 7D — Journal Handoff Hardening Baseline')
        ->and($report)->toContain('Status: Scaffolded / Baseline recorded')
        ->and($report)->toContain('Journal handoff must be idempotent, non-blocking, redacted, and explicitly configured.')
        ->and($report)->toContain('Durable activity recording remains disabled by default.')
        ->and($report)->toContain('No current Cockpit UI change is expected.')
        ->and($report)->toContain('7E — Action / Feedback Handoff Hardening Baseline')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 7D — Journal Handoff Hardening Baseline')
        ->and($cockpitCompass)->toContain('reports/124-wave-7d-journal-handoff-hardening-baseline.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 7D — Journal Handoff Hardening Baseline')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/124-wave-7d-journal-handoff-hardening-baseline.md');
});
