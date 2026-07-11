<?php

declare(strict_types=1);

it('documents cockpit wave 15e closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/184-wave-15e-closure-human-acceptance-pending.md');

    expect($report)->toContain('Wave 15A')
        ->and($report)->toContain('Wave 15B')
        ->and($report)->toContain('Wave 15C')
        ->and($report)->toContain('Wave 15D')
        ->and($report)->toContain('human acceptance pending')
        ->and($report)->toContain('22 passed')
        ->and($report)->toContain('4 passed')
        ->and($report)->toContain('checked 56')
        ->and($report)->toContain('Wave 16');
});
