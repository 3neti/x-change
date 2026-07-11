<?php

declare(strict_types=1);

it('documents cockpit wave 16 journal handoff runtime enablement', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/187-wave-16-operator-activity-journal-handoff-runtime-enablement.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 16 — Operator Activity Journal Handoff Runtime Enablement')
        ->toContain('repository=database')
        ->toContain('journal_handoff=x-journal')
        ->toContain('journal_handoff_status=recorded')
        ->toContain('CockpitDashboardJournalRecordedSmokeTest.php')
        ->toContain('Cockpit Wave 17 — Operator Activity Action Handoff Runtime Enablement')
        ->and($cockpitCompass)
        ->toContain('reports/187-wave-16-operator-activity-journal-handoff-runtime-enablement.md')
        ->toContain('journal: recorded')
        ->and($settlementCompass)
        ->toContain('../ui-cockpit/reports/187-wave-16-operator-activity-journal-handoff-runtime-enablement.md')
        ->toContain('Cockpit Wave 17 — Operator Activity Action Handoff Runtime Enablement');
});
