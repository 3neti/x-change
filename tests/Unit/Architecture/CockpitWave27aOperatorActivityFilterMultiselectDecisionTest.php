<?php

declare(strict_types=1);

it('documents cockpit wave 27a operator activity filter multiselect decision', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/199-wave-27a-operator-activity-filter-multiselect-decision.md');

    expect($report)->toContain('Keep the Operator Issuance Activity filter UI as single-select for now.')
        ->and($report)->toContain('Do not add visible multi-select controls in Wave 27.')
        ->and($report)->toContain('activity_status')
        ->and($report)->toContain('activity_handoff_status')
        ->and($report)->toContain('clearer active-filter summaries')
        ->and($report)->toContain('clear-per-filter links')
        ->and($report)->toContain('visible multi-select controls')
        ->and($report)->toContain('raw payload display')
        ->and($report)->toContain('Cockpit Wave 27B — Operator Activity Compact Active Filter Summary');
});
