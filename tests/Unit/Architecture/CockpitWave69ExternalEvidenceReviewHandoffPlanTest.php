<?php

declare(strict_types=1);

it('documents cockpit wave 69 external evidence review handoff plan', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/385-wave-69-external-evidence-review-handoff-plan.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 69 — Manual Distribution External Evidence Review / Handoff Plan')
        ->and($report)->toContain('Complete / Planning-only review and handoff baseline.')
        ->and($report)->toContain('operator-submitted review evidence')
        ->and($report)->toContain('voucher lifecycle truth')
        ->and($report)->toContain('accepted_for_review')
        ->and($report)->toContain('needs_correction')
        ->and($report)->toContain('x-journal')
        ->and($report)->toContain('x-feedback')
        ->and($report)->toContain('x-action')
        ->and($report)->toContain('x-campaign')
        ->and($report)->toContain('Mistaken recipient disclosure.')
        ->and($report)->toContain('Evidence containing secrets.')
        ->and($report)->toContain('Evidence review routes.')
        ->and($report)->toContain('Evidence journal writers.')
        ->and($report)->toContain('Money movement.')
        ->and($report)->toContain('Cockpit Wave 70 — Manual Distribution External Evidence Attachment / Storage Decision')
        ->and($cockpitCompass)->toContain('Cockpit Wave 69 — Manual Distribution External Evidence Review / Handoff Plan')
        ->and($cockpitCompass)->toContain('reports/385-wave-69-external-evidence-review-handoff-plan.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 69 — Manual Distribution External Evidence Review / Handoff Plan')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/385-wave-69-external-evidence-review-handoff-plan.md');
});
