<?php

declare(strict_types=1);

it('documents cockpit wave 15a human visual confirmation intake', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/180-wave-15a-human-visual-confirmation-intake.md');

    expect($report)->toContain('Human Visual Confirmation Intake')
        ->and($report)->toContain('/x/cockpit/quick-generate')
        ->and($report)->toContain('/x/pay-codes/create')
        ->and($report)->toContain('/x/pay-codes')
        ->and($report)->toContain('/x/balances')
        ->and($report)->toContain('Pass')
        ->and($report)->toContain('Blocked')
        ->and($report)->toContain('No raw payloads')
        ->and($report)->toContain('No unexpected mutation controls');
});
