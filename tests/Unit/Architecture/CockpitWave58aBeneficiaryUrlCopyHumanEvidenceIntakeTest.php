<?php

declare(strict_types=1);

it('documents cockpit wave 58a beneficiary url copy human evidence intake', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/350-wave-58a-beneficiary-url-copy-human-evidence-intake.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);

    expect($report)->toContain('Cockpit Wave 58A — Beneficiary URL Copy Human Evidence Intake')
        ->and($report)->toContain('Pay Code tested: 6LGM')
        ->and($report)->toContain('Voucher Detail copied value: http://x-change-sandbox.test/x/claim/6LGM/experience')
        ->and($report)->toContain('Distribution Workspace copied value: http://x-change-sandbox.test/x/claim/6LGM/experience')
        ->and($report)->toContain('Final decision supplied by reviewer: Pass')
        ->and($report)->toContain('Errors reported: none')
        ->and($report)->toContain('Cockpit Wave 58B — Beneficiary URL Copy Acceptance Decision Record');
});
