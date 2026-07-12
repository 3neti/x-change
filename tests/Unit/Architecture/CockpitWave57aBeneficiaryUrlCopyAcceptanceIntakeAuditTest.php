<?php

declare(strict_types=1);

it('documents cockpit wave 57a beneficiary url copy acceptance intake audit', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/346-wave-57a-beneficiary-url-copy-acceptance-intake-audit.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);

    expect($report)->toContain('Cockpit Wave 57A — Beneficiary URL Copy Acceptance Intake Audit')
        ->and($report)->toContain('Human acceptance result is pending')
        ->and($report)->toContain('No human reviewer evidence has been supplied yet')
        ->and($report)->toContain('reports/344-wave-56c-human-clipboard-ux-evidence-record-template.md')
        ->and($report)->toContain('does not change Cockpit UI')
        ->and($report)->toContain('Cockpit Wave 57B — Beneficiary URL Copy Intake Decision Policy');
});
