<?php

declare(strict_types=1);

it('documents cockpit mutation wave 6e action   feedback handoff default policy decision', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/115-wave-6e-action-feedback-handoff-default-policy-decision.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 6E — Action / Feedback Handoff Default Policy Decision')
        ->and($report)->toContain('Status: Scaffolded / Decision recorded')
        ->and($report)->toContain('Decide that action and feedback handoffs remain opt-in and non-default until execution/delivery semantics are explicitly authorized.')
        ->and($report)->toContain('Durable activity recording remains disabled by default.')
        ->and($report)->toContain('6F — PII Classification / Redaction Hardening Review')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 6E — Action / Feedback Handoff Default Policy Decision')
        ->and($cockpitCompass)->toContain('reports/115-wave-6e-action-feedback-handoff-default-policy-decision.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 6E — Action / Feedback Handoff Default Policy Decision')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/115-wave-6e-action-feedback-handoff-default-policy-decision.md');
});
