<?php

declare(strict_types=1);

it('documents the distribution workspace x-feedback read model closure', function () {
    $report = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/reports/534-distribution-workspace-x-feedback-read-model-slice-3-closure.md');
    $cockpitCompass = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents(dirname(__DIR__, 3).'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Distribution Workspace can now consume and render x-feedback delivery records as read-only delivery-state summaries.')
        ->and($report)->toContain('It did not send feedback')
        ->and($report)->toContain('Recipient data, provider message ids, provider payloads, raw payloads, idempotency keys, routes, and secrets remain excluded.')
        ->and($cockpitCompass)->toContain('Distribution Workspace x-feedback Read Model — Slice 3 Closure')
        ->and($cockpitCompass)->toContain('reports/534-distribution-workspace-x-feedback-read-model-slice-3-closure.md')
        ->and($settlementCompass)->toContain('Distribution Workspace x-feedback Read Model — Slice 3 Closure')
        ->and($settlementCompass)->toContain('Next recommended checkpoint: connect Distribution Workspace x-action follow-up CTA summaries as disabled read-only guidance');
});
