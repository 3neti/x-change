<?php

declare(strict_types=1);

it('documents cockpit wave 14d browser visual handoff checklist', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/178-wave-14d-browser-visual-handoff-checklist.md');

    expect($report)->toContain('/x/cockpit/quick-generate')
        ->and($report)->toContain('Diagnostics')
        ->and($report)->toContain('Show architecture history')
        ->and($report)->toContain('/x/pay-codes/create')
        ->and($report)->toContain('/x/pay-codes')
        ->and($report)->toContain('/x/balances')
        ->and($report)->toContain('Cockpit bridge')
        ->and($report)->toContain('Generate a small Pay Code')
        ->and($report)->toContain('pricing preflight')
        ->and($report)->toContain('funding preflight');
});
