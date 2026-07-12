<?php

declare(strict_types=1);

it('documents cockpit wave 63b manual copy no backend interaction regression guard', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/368-wave-63b-manual-copy-no-backend-interaction-regression-guard.md';
    $manualCopyTestPath = $packageRoot.'/tests/frontend/cockpit/CockpitManualCopyButton.test.ts';

    expect(file_exists($reportPath))->toBeTrue();
    expect(file_exists($manualCopyTestPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $manualCopyTest = file_get_contents($manualCopyTestPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 63B — Manual Copy No-Backend-Interaction Regression Guard')
        ->and($report)->toContain('tests/frontend/cockpit/CockpitManualCopyButton.test.ts')
        ->and($report)->toContain('It does not call `fetch`.')
        ->and($report)->toContain('It does not call `navigator.sendBeacon`.')
        ->and($report)->toContain('It does not instantiate or call `XMLHttpRequest`.')
        ->and($report)->toContain('Cockpit Wave 63C — Manual Copy Operational Hardening Closure')
        ->and($manualCopyTest)->toContain('does not use backend transport APIs while copying manually')
        ->and($manualCopyTest)->toContain('sendBeacon')
        ->and($manualCopyTest)->toContain('XMLHttpRequest')
        ->and($manualCopyTest)->toContain('expect(globalThis.fetch).not.toHaveBeenCalled()')
        ->and($manualCopyTest)->toContain('expect(sendBeacon).not.toHaveBeenCalled()')
        ->and($manualCopyTest)->toContain('expect(XMLHttpRequest).not.toHaveBeenCalled()')
        ->and($cockpitCompass)->toContain('Cockpit Wave 63B — Manual Copy No-Backend-Interaction Regression Guard')
        ->and($cockpitCompass)->toContain('reports/368-wave-63b-manual-copy-no-backend-interaction-regression-guard.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 63B — Manual Copy No-Backend-Interaction Regression Guard')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/368-wave-63b-manual-copy-no-backend-interaction-regression-guard.md');
});
