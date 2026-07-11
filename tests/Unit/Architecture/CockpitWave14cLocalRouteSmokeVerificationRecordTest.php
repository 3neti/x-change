<?php

declare(strict_types=1);

it('documents cockpit wave 14c local route smoke verification record', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/177-wave-14c-local-route-smoke-verification-record.md');

    expect($report)->toContain('http://x-change-sandbox.test/x/cockpit')
        ->and($report)->toContain('/x/cockpit/quick-generate')
        ->and($report)->toContain('/x/cockpit/pay-codes')
        ->and($report)->toContain('/x/pay-codes/create')
        ->and($report)->toContain('/x/pay-codes')
        ->and($report)->toContain('/x/balances')
        ->and($report)->toContain('Showing [6] routes')
        ->and($report)->toContain('Showing [4] routes')
        ->and($report)->toContain('Showing [1] routes');
});
