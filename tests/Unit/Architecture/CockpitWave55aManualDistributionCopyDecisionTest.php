<?php

declare(strict_types=1);

it('documents cockpit wave 55a manual distribution copy decision', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/337-wave-55a-manual-distribution-copy-decision.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);

    expect($report)->toContain('Cockpit Wave 55A — Manual Distribution Copy Decision')
        ->and($report)->toContain('Proceed with a browser-local manual copy affordance')
        ->and($report)->toContain('must not')
        ->and($report)->toContain('call a backend endpoint')
        ->and($report)->toContain('write journal entries')
        ->and($report)->toContain('send x-feedback deliveries')
        ->and($report)->toContain('dispatch campaigns')
        ->and($report)->toContain('move money')
        ->and($report)->toContain('Cockpit Wave 55B — Manual Copy Component Contract');
});
