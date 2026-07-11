<?php

declare(strict_types=1);

it('documents cockpit mutation wave 6f pii classification   redaction hardening review', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/116-wave-6f-pii-classification-redaction-hardening-review.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 6F — PII Classification / Redaction Hardening Review')
        ->and($report)->toContain('Status: Scaffolded / Decision recorded')
        ->and($report)->toContain('Review durable activity fields and keep raw payload/secret exposure prohibited before production default enablement.')
        ->and($report)->toContain('Durable activity recording remains disabled by default.')
        ->and($report)->toContain('6G — Production Disable / Rollback Runbook')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 6F — PII Classification / Redaction Hardening Review')
        ->and($cockpitCompass)->toContain('reports/116-wave-6f-pii-classification-redaction-hardening-review.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 6F — PII Classification / Redaction Hardening Review')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/116-wave-6f-pii-classification-redaction-hardening-review.md');
});
