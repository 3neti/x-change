<?php

declare(strict_types=1);

it('documents cockpit wave 70 external evidence attachment storage decision', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/386-wave-70-external-evidence-attachment-storage-decision.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 70 — Manual Distribution External Evidence Attachment / Storage Decision')
        ->and($report)->toContain('Attachments and screenshots remain blocked.')
        ->and($report)->toContain('attachments-blocked / text-only-evidence-first')
        ->and($report)->toContain('No evidence storage disk is authorized yet.')
        ->and($report)->toContain('Malware scanning policy.')
        ->and($report)->toContain('File type allowlist.')
        ->and($report)->toContain('structured redacted text-only evidence')
        ->and($report)->toContain('File upload controls.')
        ->and($report)->toContain('Attachment migrations.')
        ->and($report)->toContain('Storage disks.')
        ->and($report)->toContain('File scanners.')
        ->and($report)->toContain('Money movement.')
        ->and($report)->toContain('Cockpit Wave 71 — Manual Distribution External Evidence Runtime Readiness Closure')
        ->and($cockpitCompass)->toContain('Cockpit Wave 70 — Manual Distribution External Evidence Attachment / Storage Decision')
        ->and($cockpitCompass)->toContain('reports/386-wave-70-external-evidence-attachment-storage-decision.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 70 — Manual Distribution External Evidence Attachment / Storage Decision')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/386-wave-70-external-evidence-attachment-storage-decision.md');
});
