<?php

declare(strict_types=1);

it('documents the human visual browser confirmation handoff packet', function () {
    $reportPath = dirname(__DIR__, 3).'/docs/ui-cockpit/reports/037-human-visual-confirmation-handoff.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents(dirname(__DIR__, 3).'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Host Validation Checkpoint 5 — Human Visual Confirmation Handoff Packet')
        ->and($report)->toContain('Status: Human visual browser confirmation recorded as Pass')
        ->and($report)->toContain('http://x-change-sandbox.test/x/cockpit')
        ->and($report)->toContain('Visual Confirmation Form')
        ->and($report)->toContain('Allowed result values:')
        ->and($report)->toContain('Pass')
        ->and($report)->toContain('Fail')
        ->and($report)->toContain('Blocked')
        ->and($report)->toContain('This visual confirmation is recorded as `Pass`')
        ->and($report)->toContain('Mutation-capable Cockpit work still requires a separate explicit implementation plan and approval')
        ->and($report)->toContain('This checkpoint did not add:')
        ->and($report)->toContain('money movement')
        ->and($cockpitCompass)->toContain('Host Validation Checkpoint 5 — Human Visual Confirmation Handoff Packet')
        ->and($cockpitCompass)->toContain('Human visual browser confirmation is recorded as `Pass`')
        ->and($cockpitCompass)->toContain('reports/037-human-visual-confirmation-handoff.md')
        ->and($settlementCompass)->toContain('x-change Host Validation Checkpoint 5 — Human Visual Confirmation Handoff Packet')
        ->and($settlementCompass)->toContain('Mutation-capable Cockpit implementation still requires a separate explicit plan and approval');
});
