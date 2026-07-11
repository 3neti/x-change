<?php

declare(strict_types=1);

it('documents cockpit wave 15d next runtime decision', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/183-wave-15d-next-runtime-decision-record.md');

    expect($report)->toContain('Decision')
        ->and($report)->toContain('conditional-go')
        ->and($report)->toContain('No new mutation expansion')
        ->and($report)->toContain('human visual acceptance is marked Pass')
        ->and($report)->toContain('Wave 16')
        ->and($report)->toContain('Operator Activity Journal Handoff Runtime Enablement')
        ->and($report)->toContain('Quick Generate remains the only Cockpit mutation path');
});
