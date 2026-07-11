<?php

declare(strict_types=1);

it('documents cockpit wave 23 runtime profile operator acceptance closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/194-wave-23-runtime-profile-operator-acceptance-closure.md');

    expect($report)->toContain('Accepted')
        ->and($report)->toContain('/x/cockpit/diagnostics/runtime-profile')
        ->and($report)->toContain('Operator Activity Runtime Profile')
        ->and($report)->toContain('read-only Cockpit operator diagnostics surface')
        ->and($report)->toContain('1 passed, 19 assertions')
        ->and($report)->toContain('Asset drift doctor: checked 58, ok 58, stale 0, missing 0, extra 0')
        ->and($report)->toContain('partially_wired')
        ->and($report)->toContain('Runtime configuration mutation is not authorized from Cockpit')
        ->and($report)->toContain('No provider, wallet, voucher, or money movement behavior changes')
        ->and($report)->toContain('Enable handoffs')
        ->and($report)->toContain('Save configuration')
        ->and($report)->toContain('Wave 23A closes Runtime Profile operator acceptance as a pass')
        ->and($report)->toContain('Cockpit Wave 23B — Next Runtime Decision Record');
});
