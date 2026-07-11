<?php

declare(strict_types=1);

it('documents cockpit wave 17 action handoff runtime enablement', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/188-wave-17-operator-activity-action-handoff-runtime-enablement.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 17 — Operator Activity Action Handoff Runtime Enablement')
        ->toContain('action_handoff=x-action')
        ->toContain('action_handoff_status=composed')
        ->toContain('executes_action=false')
        ->toContain('CockpitDashboardActionComposedSmokeTest.php')
        ->toContain('Cockpit Wave 18 — Operator Activity Feedback Handoff Runtime Enablement')
        ->and($cockpitCompass)
        ->toContain('reports/188-wave-17-operator-activity-action-handoff-runtime-enablement.md')
        ->toContain('action: composed')
        ->and($settlementCompass)
        ->toContain('../ui-cockpit/reports/188-wave-17-operator-activity-action-handoff-runtime-enablement.md')
        ->toContain('Cockpit Wave 18 — Operator Activity Feedback Handoff Runtime Enablement');
});
