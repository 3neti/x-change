<?php

declare(strict_types=1);

it('documents cockpit mutation wave 7f pii redaction enforcement baseline', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/126-wave-7f-pii-redaction-enforcement-baseline.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 7F — PII / Redaction Enforcement Baseline')
        ->and($report)->toContain('Status: Scaffolded / Baseline recorded')
        ->and($report)->toContain('Durable activity storage and display must not expose raw payloads, credentials, wallet data, tokens, OTPs, or recipient secrets.')
        ->and($report)->toContain('Durable activity recording remains disabled by default.')
        ->and($report)->toContain('No current Cockpit UI change is expected.')
        ->and($report)->toContain('7G — Rollback / Disable Operational Baseline')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 7F — PII / Redaction Enforcement Baseline')
        ->and($cockpitCompass)->toContain('reports/126-wave-7f-pii-redaction-enforcement-baseline.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 7F — PII / Redaction Enforcement Baseline')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/126-wave-7f-pii-redaction-enforcement-baseline.md');
});
