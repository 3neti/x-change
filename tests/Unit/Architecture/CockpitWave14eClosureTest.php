<?php

declare(strict_types=1);

it('documents cockpit wave 14e closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/179-wave-14e-closure-next-planning-record.md');

    expect($report)->toContain('Wave 14A')
        ->and($report)->toContain('Wave 14B')
        ->and($report)->toContain('Wave 14C')
        ->and($report)->toContain('Wave 14D')
        ->and($report)->toContain('checked 56')
        ->and($report)->toContain('stale 0')
        ->and($report)->toContain('22 passed')
        ->and($report)->toContain('7 passed')
        ->and($report)->toContain('Wave 15');
});
