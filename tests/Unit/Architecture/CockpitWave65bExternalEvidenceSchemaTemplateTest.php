<?php

declare(strict_types=1);

it('documents cockpit wave 65b external evidence schema template', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/374-wave-65b-external-evidence-schema-template.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 65B — Manual Distribution External Evidence Schema / Template')
        ->and($report)->toContain('planning-only template')
        ->and($report)->toContain('This is not a database schema, request contract, upload endpoint, or runtime DTO.')
        ->and($report)->toContain('x-change.cockpit.manual-distribution-external-evidence.planning.v1')
        ->and($report)->toContain('external_workflow_name:')
        ->and($report)->toContain('recipient_verification_method:')
        ->and($report)->toContain('delivery_reference_redacted:')
        ->and($report)->toContain('attachments_allowed: false')
        ->and($report)->toContain('raw_payload_allowed: false')
        ->and($report)->toContain('lifecycle_truth: false')
        ->and($report)->toContain('OTP values.')
        ->and($report)->toContain('Raw message bodies.')
        ->and($report)->toContain('Unredacted recipient PII.')
        ->and($report)->toContain('Allowed Planning States')
        ->and($report)->toContain('This template does not create:')
        ->and($report)->toContain('Cockpit Wave 65C — Manual Distribution External Evidence Intake Closure')
        ->and($cockpitCompass)->toContain('Cockpit Wave 65B — Manual Distribution External Evidence Schema / Template')
        ->and($cockpitCompass)->toContain('reports/374-wave-65b-external-evidence-schema-template.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 65B — Manual Distribution External Evidence Schema / Template')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/374-wave-65b-external-evidence-schema-template.md');
});
