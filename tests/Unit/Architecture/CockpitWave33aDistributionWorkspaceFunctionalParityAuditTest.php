<?php

declare(strict_types=1);

it('documents cockpit wave 33a distribution workspace functional parity audit', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/225-wave-33a-distribution-workspace-functional-parity-audit.md');

    expect($report)->toContain('Cockpit Wave 33A — Distribution Workspace Functional Parity Audit')
        ->and($report)->toContain('/x/cockpit/pay-codes/{code}/distribution')
        ->and($report)->toContain('read-only operator surface')
        ->and($report)->toContain('share targets')
        ->and($report)->toContain('QR / short-link / copy-text readiness')
        ->and($report)->toContain('must not')
        ->and($report)->toContain('dispatch SMS')
        ->and($report)->toContain('Cockpit Wave 33B — Distribution Workspace Read Model Contract');
});
