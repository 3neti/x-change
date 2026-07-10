<?php

declare(strict_types=1);

it('documents cockpit mutation wave 5 closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/108-cockpit-mutation-wave-5-closure-report.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Wave 5L — Cockpit Mutation Wave 5 Closure Report')
        ->and($report)->toContain('Status: Closed pending manual UI review')
        ->and($report)->toContain('198ff60 cash: normalize monetary floats before BrickMoney')
        ->and($report)->toContain('7a6889e voucher: verify cash persistence avoids BrickMath floats')
        ->and($report)->toContain('b4956d3 cockpit: record upstream brickmath fix execution')
        ->and($report)->toContain('73c21da cockpit: verify brickmath monetary warning is resolved')
        ->and($report)->toContain('2230ab6 cockpit: clean up local diagnostic fixture')
        ->and($report)->toContain('d704c40 cockpit: close durable activity local opt in')
        ->and($report)->toContain('e98ac94 cockpit: defer durable activity production default')
        ->and($report)->toContain('PC-LOCAL-DIAGNOSTIC removed.')
        ->and($report)->toContain('MCPC count: 1')
        ->and($report)->toContain('http://x-change-sandbox.test/x/cockpit')
        ->and($report)->toContain('Cockpit Mutation Wave 6 — Production Hardening Plan')
        ->and($report)->toContain('Wave 6A — Durable Activity Authorization / Tenant Scope Decision')
        ->and($report)->toContain('No further Wave 5 implementation checkpoints remain.')
        ->and($cockpitCompass)->toContain('Wave 5L — Cockpit Mutation Wave 5 Closure Report')
        ->and($cockpitCompass)->toContain('reports/108-cockpit-mutation-wave-5-closure-report.md')
        ->and($settlementCompass)->toContain('Wave 5L — Cockpit Mutation Wave 5 Closure Report')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/108-cockpit-mutation-wave-5-closure-report.md');
});
