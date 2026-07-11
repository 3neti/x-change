<?php

declare(strict_types=1);

it('documents cockpit mutation wave 6b durable activity retention   purge policy decision', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/112-wave-6b-durable-activity-retention-purge-policy-decision.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 6B — Durable Activity Retention / Purge Policy Decision')
        ->and($report)->toContain('Status: Scaffolded / Decision recorded')
        ->and($report)->toContain('Decide that durable activity requires an explicit retention window and purge process before production default enablement.')
        ->and($report)->toContain('Durable activity recording remains disabled by default.')
        ->and($report)->toContain('6C — Recorder Failure Observability Baseline')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 6B — Durable Activity Retention / Purge Policy Decision')
        ->and($cockpitCompass)->toContain('reports/112-wave-6b-durable-activity-retention-purge-policy-decision.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 6B — Durable Activity Retention / Purge Policy Decision')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/112-wave-6b-durable-activity-retention-purge-policy-decision.md');
});
