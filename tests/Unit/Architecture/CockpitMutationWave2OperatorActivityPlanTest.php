<?php

declare(strict_types=1);

it('documents the cockpit mutation wave 2 operator activity and audit handoff plan without runtime behavior', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/049-operator-visible-issuance-activity-audit-handoff-plan.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 2 — Operator-visible Issuance Activity and Audit Handoff Plan')
        ->and($report)->toContain('Status: Plan drafted; no implementation authorized in this slice')
        ->and($report)->toContain('Wave 2A — Operator Issuance Activity Read Model Contract')
        ->and($report)->toContain('Wave 2B — Issuance Activity Recorder Boundary')
        ->and($report)->toContain('Wave 2C — Journal Handoff Boundary')
        ->and($report)->toContain('Wave 2D — Action Handoff Boundary')
        ->and($report)->toContain('This planning slice does not add:')
        ->and($report)->toContain('journal writes')
        ->and($report)->toContain('action execution')
        ->and($report)->toContain('feedback delivery')
        ->and($report)->toContain('money movement')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 2 — Operator-visible Issuance Activity and Audit Handoff Plan')
        ->and($cockpitCompass)->toContain('reports/049-operator-visible-issuance-activity-audit-handoff-plan.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 2 — Operator-visible Issuance Activity and Audit Handoff Plan')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/049-operator-visible-issuance-activity-audit-handoff-plan.md');
});
