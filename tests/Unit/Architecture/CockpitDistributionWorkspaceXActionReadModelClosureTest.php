<?php

declare(strict_types=1);

it('documents the distribution workspace x-action read model closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/537-distribution-workspace-x-action-read-model-slice-3-closure.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Distribution Workspace can now display connected x-action follow-up CTA summaries as disabled read-only guidance.')
        ->toContain('It did not execute x-action actions')
        ->toContain('Action run objects, handoff payloads, target parameters, unsafe URLs, raw diagnostics, provider payloads, raw payloads, wallet data, and secrets remain excluded.')
        ->and($cockpitCompass)->toContain('Distribution Workspace x-action Read Model — Slice 3 Closure')
        ->and($cockpitCompass)->toContain('reports/537-distribution-workspace-x-action-read-model-slice-3-closure.md')
        ->and($settlementCompass)->toContain('Distribution Workspace x-action Read Model — Slice 3 Closure')
        ->and($settlementCompass)->toContain('Next recommended checkpoint: connect Distribution Workspace x-journal evidence summaries as read-only audit guidance');
});
