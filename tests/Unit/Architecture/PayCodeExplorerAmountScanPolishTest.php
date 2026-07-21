<?php

declare(strict_types=1);

it('documents pay code explorer amount scan polish slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/592-pay-code-explorer-amount-scan-polish-slice-1.md');
    $closure = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/593-pay-code-explorer-amount-scan-polish-slice-2-closure.md');
    $component = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $hostComponent = file_get_contents($packageRoot.'/../../resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Pay Code Explorer Amount Scan Polish — Slice 1')
        ->toContain('Right-aligned the desktop `Amount` column')
        ->toContain('Presentation-only amount scan polish')
        ->and($component)->toContain('cockpit-pay-code-amount')
        ->and($component)->toContain('cockpit-pay-code-mobile-amount')
        ->and($component)->toContain('text-right font-mono')
        ->and($component)->toContain('tabular-nums')
        ->and($hostComponent)->toContain('cockpit-pay-code-amount')
        ->and($hostComponent)->toContain('cockpit-pay-code-mobile-amount')
        ->and($hostComponent)->toContain('text-right font-mono')
        ->and($hostComponent)->toContain('tabular-nums')
        ->and($frontendTest)->toContain('renders scan-friendly amount values')
        ->and($frontendTest)->toContain('cockpit-pay-code-mobile-amount')
        ->and($closure)->toContain('Pay Code Explorer Amount Scan Polish — Slice 2 Closure')
        ->and($closure)->toContain('php artisan x-change:doctor --assets --no-interaction')
        ->and($closure)->toContain('could not connect to ChromeDriver')
        ->and($closure)->toContain('bind() failed: Operation not permitted (1)')
        ->and($closure)->toContain('pending for a less-restricted shell')
        ->and($closure)->toContain('Sanitized formatted amount strings remain unchanged.')
        ->and($cockpitCompass)->toContain('Pay Code Explorer Amount Scan Polish Slice 1')
        ->and($cockpitCompass)->toContain('reports/592-pay-code-explorer-amount-scan-polish-slice-1.md')
        ->and($cockpitCompass)->toContain('Pay Code Explorer Amount Scan Polish Slice 2')
        ->and($cockpitCompass)->toContain('reports/593-pay-code-explorer-amount-scan-polish-slice-2-closure.md')
        ->and($settlementCompass)->toContain('Pay Code Explorer Amount Scan Polish — Slice 1')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/592-pay-code-explorer-amount-scan-polish-slice-1.md')
        ->and($settlementCompass)->toContain('Pay Code Explorer Amount Scan Polish — Slice 2')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/593-pay-code-explorer-amount-scan-polish-slice-2-closure.md');
});
