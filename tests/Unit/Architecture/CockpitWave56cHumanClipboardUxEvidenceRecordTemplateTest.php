<?php

declare(strict_types=1);

it('documents cockpit wave 56c human clipboard ux evidence record template', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/344-wave-56c-human-clipboard-ux-evidence-record-template.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);

    expect($report)->toContain('Cockpit Wave 56C — Human Clipboard UX Evidence Record Template')
        ->and($report)->toContain('Voucher Detail copied clipboard value')
        ->and($report)->toContain('Distribution Workspace copied clipboard value')
        ->and($report)->toContain('Final decision: Pass / Blocked / Fail')
        ->and($report)->toContain('Pass only when')
        ->and($report)->toContain('Blocked Criteria')
        ->and($report)->toContain('Fail Criteria')
        ->and($report)->toContain('Cockpit Wave 56D — Manual Clipboard UX Acceptance Closure');
});
