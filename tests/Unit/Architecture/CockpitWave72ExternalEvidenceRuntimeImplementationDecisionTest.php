<?php

declare(strict_types=1);

it('documents cockpit wave 72 external evidence runtime implementation decision', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/388-wave-72-external-evidence-runtime-implementation-decision.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 72 — Manual Distribution External Evidence Runtime Implementation Decision')
        ->and($report)->toContain('Complete / Runtime implementation not authorized.')
        ->and($report)->toContain('planning-track-complete / runtime-deferred')
        ->and($report)->toContain('Runtime implementation is deferred.')
        ->and($report)->toContain('Runtime journal handoff adapter.')
        ->and($report)->toContain('structured redacted text-only external evidence intake')
        ->and($report)->toContain('Journal-ready but not journal-dependent.')
        ->and($report)->toContain('Feedback-correlatable but not feedback-mutating.')
        ->and($report)->toContain('Runtime evidence routes.')
        ->and($report)->toContain('Runtime evidence migrations.')
        ->and($report)->toContain('Runtime journal writers.')
        ->and($report)->toContain('Money movement.')
        ->and($report)->toContain('checked 59')
        ->and($report)->toContain('ok 59')
        ->and($report)->toContain('This closes the manual distribution external evidence planning track.')
        ->and($report)->toContain('Cockpit Next Capability Selection')
        ->and($cockpitCompass)->toContain('Cockpit Wave 72 — Manual Distribution External Evidence Runtime Implementation Decision')
        ->and($cockpitCompass)->toContain('reports/388-wave-72-external-evidence-runtime-implementation-decision.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 72 — Manual Distribution External Evidence Runtime Implementation Decision')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/388-wave-72-external-evidence-runtime-implementation-decision.md');
});
