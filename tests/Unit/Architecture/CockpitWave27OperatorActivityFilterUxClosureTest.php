<?php

declare(strict_types=1);

it('documents cockpit wave 27 operator activity filter ux refinement closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/200-wave-27-operator-activity-filter-ux-refinement-closure.md');

    expect($report)->toContain('Cockpit Wave 27 — Operator Activity Filter UX Refinement / Multi-Select Decision')
        ->and($report)->toContain('reports/199-wave-27a-operator-activity-filter-multiselect-decision.md')
        ->and($report)->toContain('Filters: search “money changer” · status issued · handoff recorded')
        ->and($report)->toContain('Clear search')
        ->and($report)->toContain('Clear status')
        ->and($report)->toContain('Clear handoff')
        ->and($report)->toContain('checked 58, ok 58, stale 0, missing 0, extra 0')
        ->and($report)->toContain('Dusk activity filter smoke: 1 passed, 23 assertions')
        ->and($report)->toContain('visible multi-select controls')
        ->and($report)->toContain('raw payload display')
        ->and($report)->toContain('Cockpit Wave 28 — Operator Activity Filter Browser Acceptance / Next Runtime Decision');
});
