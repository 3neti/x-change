<?php

declare(strict_types=1);

it('documents cockpit wave 58b beneficiary url copy acceptance decision record', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/351-wave-58b-beneficiary-url-copy-acceptance-decision-record.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);

    expect($report)->toContain('Cockpit Wave 58B — Beneficiary URL Copy Acceptance Decision Record')
        ->and($report)->toContain('Pass')
        ->and($report)->toContain('Pay Code tested: 6LGM')
        ->and($report)->toContain('Voucher Detail copied value: http://x-change-sandbox.test/x/claim/6LGM/experience')
        ->and($report)->toContain('Distribution Workspace copied value: http://x-change-sandbox.test/x/claim/6LGM/experience')
        ->and($report)->toContain('This decision accepts the manual copy UX only')
        ->and($report)->toContain('Cockpit Wave 58C — Beneficiary URL Copy Acceptance Compass Update');
});
