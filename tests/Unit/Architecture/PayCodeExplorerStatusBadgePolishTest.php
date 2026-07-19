<?php

declare(strict_types=1);

it('documents pay code explorer status badge polish slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/590-pay-code-explorer-status-badge-polish-slice-1.md');
    $component = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts');

    expect($report)
        ->toContain('Pay Code Explorer Status Badge Polish — Slice 1')
        ->toContain('operator-facing Title Case')
        ->toContain('Presentation-only status badge polish')
        ->and($component)->toContain('function displayStatus')
        ->and($component)->toContain('function statusBadgeClass')
        ->and($component)->toContain('cockpit-pay-code-status-badge')
        ->and($component)->toContain('cockpit-pay-code-mobile-status-badge')
        ->and($component)->toContain('bg-emerald-50')
        ->and($component)->toContain('bg-amber-50')
        ->and($component)->toContain('bg-rose-50')
        ->and($frontendTest)->toContain('renders scan-friendly status badges')
        ->and($frontendTest)->toContain('Awaiting Approval');
});
