<?php

declare(strict_types=1);

it('documents the pending human result policy', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/314-wave-50b-pending-human-result-policy.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 50B')
        ->toContain('Completed')
        ->toContain('operator acceptance must not be reported as complete')
        ->toContain('non-mutating functional work may continue')
        ->toContain('Enabling new campaign mutation');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 50B result: Pending human result policy completed')
        ->toContain('Cockpit Wave 50C — Campaign Destination Follow-up Decision Matrix');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 50B — Pending Human Result Policy')
        ->toContain('Cockpit Wave 50C — Campaign Destination Follow-up Decision Matrix');
});
