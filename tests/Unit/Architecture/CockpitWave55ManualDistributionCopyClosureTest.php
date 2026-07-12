<?php

declare(strict_types=1);

it('documents and publishes cockpit wave 55 manual distribution copy closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $hostRoot = dirname($packageRoot, 2);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/341-wave-55-manual-distribution-copy-closure.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');
    $hostComponent = file_get_contents($hostRoot.'/resources/js/cockpit/components/CockpitManualCopyButton.vue');
    $hostVoucherDetail = file_get_contents($hostRoot.'/resources/js/cockpit/pages/VoucherDetail.vue');
    $hostDistributionWorkspace = file_get_contents($hostRoot.'/resources/js/cockpit/pages/DistributionWorkspace.vue');

    expect($report)->toContain('Cockpit Wave 55 — Manual Distribution Copy Closure')
        ->and($report)->toContain('navigator.clipboard.writeText')
        ->and($report)->toContain('checked: 59')
        ->and($report)->toContain('stale: 0')
        ->and($report)->toContain('Cockpit Wave 56 — Manual Distribution Human Browser Verification / Clipboard UX Acceptance')
        ->and($cockpitCompass)->toContain('Cockpit Wave 55 — Manual Distribution Copy Closure')
        ->and($cockpitCompass)->toContain('reports/341-wave-55-manual-distribution-copy-closure.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 55 complete')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/341-wave-55-manual-distribution-copy-closure.md')
        ->and($hostComponent)->toContain('cockpit-manual-copy-button')
        ->and($hostComponent)->not->toContain('fetch(')
        ->and($hostVoucherDetail)->toContain('CockpitManualCopyButton')
        ->and($hostDistributionWorkspace)->toContain('CockpitManualCopyButton');
});
