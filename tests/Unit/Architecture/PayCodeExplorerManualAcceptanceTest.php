<?php

declare(strict_types=1);

it('documents pay code explorer manual acceptance slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $checklist = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/578-pay-code-explorer-manual-acceptance-slice-1-checklist.md');
    $browserTest = file_get_contents($packageRoot.'/../../tests/Browser/CockpitPayCodeExplorerFilterSmokeTest.php');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($checklist)
        ->toContain('Pay Code Explorer Manual Acceptance — Slice 1 Checklist')
        ->toContain('/x/cockpit/pay-codes')
        ->toContain('Search and status filtering feel like read-only list navigation.')
        ->toContain('Result rows are scan-friendly on desktop.')
        ->toContain('Result cards are scan-friendly on mobile or narrow widths.')
        ->toContain('Do not record human `Pass` from automated tests alone.')
        ->and($browserTest)->toContain('/x/cockpit/pay-codes?search=PC-DUSK-FILTER&status=redeemed')
        ->and($browserTest)->toContain('assertQueryStringHas')
        ->and($browserTest)->toContain('CURRENT SEARCH')
        ->and($browserTest)->toContain('Filters use read-only GET navigation.')
        ->and($browserTest)->toContain('assertDontSee')
        ->and($browserTest)->toContain('provider_payload')
        ->and($browserTest)->toContain('raw_payload')
        ->and($cockpitCompass)->toContain('Pay Code Explorer Manual Acceptance Slice 1')
        ->and($cockpitCompass)->toContain('reports/578-pay-code-explorer-manual-acceptance-slice-1-checklist.md')
        ->and($settlementCompass)->toContain('Pay Code Explorer Manual Acceptance — Slice 1')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/578-pay-code-explorer-manual-acceptance-slice-1-checklist.md');
});
