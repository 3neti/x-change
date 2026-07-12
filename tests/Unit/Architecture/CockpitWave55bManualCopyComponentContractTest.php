<?php

declare(strict_types=1);

it('documents cockpit wave 55b manual copy component contract', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/338-wave-55b-manual-copy-component-contract.md';
    $componentPath = $packageRoot.'/resources/js/cockpit/components/CockpitManualCopyButton.vue';
    $frontendTestPath = $packageRoot.'/tests/frontend/cockpit/CockpitManualCopyButton.test.ts';

    expect(file_exists($reportPath))->toBeTrue();
    expect(file_exists($componentPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $component = file_get_contents($componentPath);
    $frontendTest = file_get_contents($frontendTestPath);

    expect($report)->toContain('Cockpit Wave 55B — Manual Copy Component Contract')
        ->and($report)->toContain('navigator.clipboard.writeText')
        ->and($report)->toContain('does not call `fetch`')
        ->and($report)->toContain('Cockpit Wave 55C — Voucher Detail Manual Copy Adoption')
        ->and($component)->toContain('data-testid="cockpit-manual-copy-button"')
        ->and($component)->toContain('navigator?.clipboard')
        ->and($component)->not->toContain('fetch(')
        ->and($frontendTest)->toContain('not.toHaveBeenCalled');
});
