<?php

declare(strict_types=1);

it('documents the cockpit durable activity diagnostic fixture seeded visual verification plan', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/090-durable-activity-diagnostic-fixture-seeded-visual-verification-plan.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 4N — Durable Activity Diagnostic Fixture / Seeded Visual Verification Plan')
        ->and($report)->toContain('Status: Plan recorded')
        ->and($report)->toContain('No operator issuance activity available')
        ->and($report)->toContain('metadata.journal_handoff.diagnostic')
        ->and($report)->toContain('php artisan x-change:cockpit:seed-diagnostic-activity --local-only')
        ->and($report)->toContain('refuse to run in production')
        ->and($report)->toContain('require an explicit `--local-only` flag')
        ->and($report)->toContain('write only to the package-owned durable activity table')
        ->and($report)->toContain('avoid invoking x-journal')
        ->and($report)->toContain('avoid creating journal entries')
        ->and($report)->toContain('avoid executing actions')
        ->and($report)->toContain('avoid sending feedback')
        ->and($report)->toContain('avoid calling providers')
        ->and($report)->toContain('avoid voucher mutation')
        ->and($report)->toContain('avoid wallet access')
        ->and($report)->toContain('avoid money movement')
        ->and($report)->toContain('retry button')
        ->and($report)->toContain('raw payload')
        ->and($report)->toContain('Cockpit Mutation Wave 4O — Local Durable Activity Diagnostic Fixture Implementation')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 4N — Durable Activity Diagnostic Fixture / Seeded Visual Verification Plan')
        ->and($cockpitCompass)->toContain('reports/090-durable-activity-diagnostic-fixture-seeded-visual-verification-plan.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 4N — Durable Activity Diagnostic Fixture / Seeded Visual Verification Plan')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/090-durable-activity-diagnostic-fixture-seeded-visual-verification-plan.md');
});
