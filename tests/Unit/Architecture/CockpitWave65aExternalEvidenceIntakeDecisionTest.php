<?php

declare(strict_types=1);

it('documents cockpit wave 65a external evidence intake decision', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/373-wave-65a-external-evidence-intake-decision.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 65A — Manual Distribution External Evidence Intake Decision')
        ->and($report)->toContain('Proceed only with an evidence-intake planning baseline.')
        ->and($report)->toContain('Do not implement evidence persistence')
        ->and($report)->toContain('Manual copy is operational and accepted.')
        ->and($report)->toContain('External workflows own actual delivery.')
        ->and($report)->toContain('x-feedback owns communication delivery state when wired.')
        ->and($report)->toContain('x-journal owns durable audit facts when explicitly handed off.')
        ->and($report)->toContain('x-action owns workflow continuation state.')
        ->and($report)->toContain('Which approved external workflow was used.')
        ->and($report)->toContain('A redacted delivery reference.')
        ->and($report)->toContain('Lifecycle truth.')
        ->and($report)->toContain('x-feedback delivery truth unless x-feedback supplied it.')
        ->and($report)->toContain('planning-only / no-intake-runtime')
        ->and($report)->toContain('Cockpit Wave 65B — Manual Distribution External Evidence Schema / Template')
        ->and($cockpitCompass)->toContain('Cockpit Wave 65A — Manual Distribution External Evidence Intake Decision')
        ->and($cockpitCompass)->toContain('reports/373-wave-65a-external-evidence-intake-decision.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 65A — Manual Distribution External Evidence Intake Decision')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/373-wave-65a-external-evidence-intake-decision.md');
});
