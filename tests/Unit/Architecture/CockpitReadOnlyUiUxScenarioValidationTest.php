<?php

declare(strict_types=1);

it('documents the read-only cockpit ui ux scenario validation checkpoint', function () {
    $reportPath = dirname(__DIR__, 3).'/docs/ui-cockpit/reports/033-read-only-ui-ux-scenario-validation.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents(dirname(__DIR__, 3).'/docs/architecture/SETTLEMENT_OS_COMPASS.md');
    $frontendTest = file_get_contents(dirname(__DIR__, 3).'/tests/frontend/cockpit/CockpitReadOnlyScenarioValidation.test.ts');

    expect($report)->toContain('Host Validation Checkpoint 1 — Read-Only Cockpit UI/UX Scenario Validation')
        ->and($report)->toContain('basic_cash')
        ->and($report)->toContain('divisible_open_three_slices_enforced_interval')
        ->and($report)->toContain('This checkpoint did not run lifecycle scenarios')
        ->and($report)->toContain('move money')
        ->and($frontendTest)->toContain('Cockpit read-only UI/UX scenario validation checkpoint')
        ->and($frontendTest)->toContain('basic_cash')
        ->and($frontendTest)->toContain('divisible_open_three_slices_enforced_interval')
        ->and($frontendTest)->toContain('expectNoUnsafeText')
        ->and($cockpitCompass)->toContain('Completed Host Validation Checkpoint 1 — Read-Only Cockpit UI/UX Scenario Validation')
        ->and($cockpitCompass)->toContain('reports/033-read-only-ui-ux-scenario-validation.md')
        ->and($settlementCompass)->toContain('x-change Host Validation Checkpoint 1 — Read-Only Cockpit UI/UX Scenario Validation')
        ->and($settlementCompass)->toContain('The validation harness covers Dashboard, Pay Code Explorer, and Voucher Detail');
});
