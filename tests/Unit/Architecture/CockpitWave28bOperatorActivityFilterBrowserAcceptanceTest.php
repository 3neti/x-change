<?php

declare(strict_types=1);

it('documents cockpit wave 28b operator activity filter browser acceptance', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/201-wave-28b-operator-activity-filter-browser-acceptance.md');

    expect($report)->toContain('/x/cockpit?activity_search=PC-DUSK-FILTER&activity_status=issued&activity_handoff_status=recorded')
        ->and($report)->toContain('Clear search')
        ->and($report)->toContain('Clear status')
        ->and($report)->toContain('Clear handoff')
        ->and($report)->toContain('Dusk activity filter smoke: 1 passed, 27 assertions')
        ->and($report)->toContain('Enable handoffs')
        ->and($report)->toContain('Save configuration')
        ->and($report)->toContain('raw payload display')
        ->and($report)->toContain('Cockpit Wave 28C — Operator Activity Next Runtime Decision');
});
