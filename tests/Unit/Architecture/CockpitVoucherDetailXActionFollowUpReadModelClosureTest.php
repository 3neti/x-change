<?php

declare(strict_types=1);

it('documents the voucher detail x-action follow-up read model closure', function () {
    $report = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/reports/531-voucher-detail-x-action-follow-up-read-model-slice-3-closure.md');
    $cockpitCompass = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents(dirname(__DIR__, 3).'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Voucher Detail can now consume and render x-action follow-up CTA summaries as disabled read-only operator guidance.')
        ->and($report)->toContain('It did not execute x-action actions')
        ->and($report)->toContain('Run IDs, handoff payloads, target parameters, raw diagnostics, unsafe URLs, provider payloads, and secrets remain excluded.')
        ->and($cockpitCompass)->toContain('Voucher Detail x-action Follow-up Read Model — Slice 3 Closure')
        ->and($cockpitCompass)->toContain('reports/531-voucher-detail-x-action-follow-up-read-model-slice-3-closure.md')
        ->and($settlementCompass)->toContain('Voucher Detail x-action Follow-up Read Model — Slice 3 Closure')
        ->and($settlementCompass)->toContain('Next recommended checkpoint: move connected-service summaries into Distribution Workspace, starting with x-feedback delivery state.');
});
