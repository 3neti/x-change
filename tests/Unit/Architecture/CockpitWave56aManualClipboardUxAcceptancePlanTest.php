<?php

declare(strict_types=1);

it('documents cockpit wave 56a manual clipboard ux acceptance plan', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/342-wave-56a-manual-clipboard-ux-acceptance-plan.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);

    expect($report)->toContain('Cockpit Wave 56A — Manual Clipboard UX Acceptance Plan')
        ->and($report)->toContain('/x/cockpit/pay-codes/{code}')
        ->and($report)->toContain('/x/cockpit/pay-codes/{code}/distribution')
        ->and($report)->toContain('Copy beneficiary URL')
        ->and($report)->toContain('copied clipboard value matches the visible beneficiary URL')
        ->and($report)->toContain('No SMS, email, webhook, in-app notification')
        ->and($report)->toContain('Cockpit Wave 56B — Automated Clipboard UX Evidence Guard');
});
