<?php

declare(strict_types=1);

it('documents cockpit mutation wave 7e action feedback handoff hardening baseline', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/125-wave-7e-action-feedback-handoff-hardening-baseline.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 7E — Action / Feedback Handoff Hardening Baseline')
        ->and($report)->toContain('Status: Scaffolded / Baseline recorded')
        ->and($report)->toContain('Action and feedback handoffs must be hints/communication preparation only unless an explicit later mutation slice authorizes execution or delivery.')
        ->and($report)->toContain('Durable activity recording remains disabled by default.')
        ->and($report)->toContain('No current Cockpit UI change is expected.')
        ->and($report)->toContain('7F — PII / Redaction Enforcement Baseline')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 7E — Action / Feedback Handoff Hardening Baseline')
        ->and($cockpitCompass)->toContain('reports/125-wave-7e-action-feedback-handoff-hardening-baseline.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 7E — Action / Feedback Handoff Hardening Baseline')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/125-wave-7e-action-feedback-handoff-hardening-baseline.md');
});
