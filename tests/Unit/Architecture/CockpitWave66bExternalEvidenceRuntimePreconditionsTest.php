<?php

declare(strict_types=1);

it('documents cockpit wave 66b external evidence runtime preconditions', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/377-wave-66b-external-evidence-runtime-preconditions.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 66B — Manual Distribution External Evidence Runtime Preconditions')
        ->and($report)->toContain('Authorization gate for creating evidence.')
        ->and($report)->toContain('Authorization gate for viewing evidence.')
        ->and($report)->toContain('Tenant and operator scoping strategy.')
        ->and($report)->toContain('Redaction policy for submitted references and notes.')
        ->and($report)->toContain('Journal handoff decision.')
        ->and($report)->toContain('x-feedback correlation decision.')
        ->and($report)->toContain('x-action continuation decision.')
        ->and($report)->toContain('x-campaign attribution decision.')
        ->and($report)->toContain('A final request contract.')
        ->and($report)->toContain('A rollback plan.')
        ->and($report)->toContain('runtime-blocked / preconditions-required')
        ->and($report)->toContain('Do not add evidence forms, routes, controllers')
        ->and($report)->toContain('Cockpit Wave 66C — Manual Distribution External Evidence Runtime Decision Closure')
        ->and($cockpitCompass)->toContain('Cockpit Wave 66B — Manual Distribution External Evidence Runtime Preconditions')
        ->and($cockpitCompass)->toContain('reports/377-wave-66b-external-evidence-runtime-preconditions.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 66B — Manual Distribution External Evidence Runtime Preconditions')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/377-wave-66b-external-evidence-runtime-preconditions.md');
});
