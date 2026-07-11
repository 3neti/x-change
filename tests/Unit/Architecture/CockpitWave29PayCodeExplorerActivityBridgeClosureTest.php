<?php

declare(strict_types=1);

it('documents cockpit wave 29 pay code explorer activity bridge closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/205-wave-29-pay-code-explorer-activity-bridge-closure.md');

    expect($report)->toContain('Cockpit Wave 29 — Pay Code Explorer Activity Bridge Closure')
        ->and($report)->toContain('reports/204-wave-29a-pay-code-explorer-runtime-parity-audit.md')
        ->and($report)->toContain('activity_navigation_context')
        ->and($report)->toContain('Open in Explorer')
        ->and($report)->toContain('/x/cockpit/pay-codes?activity_code={code}&activity_source=operator_issuance_activity')
        ->and($report)->toContain('Dusk bridge smoke: 1 passed, 22 assertions')
        ->and($report)->toContain('Asset doctor: checked 58, ok 58, stale 0, missing 0, extra 0')
        ->and($report)->toContain('mutate vouchers')
        ->and($report)->toContain('render raw payloads')
        ->and($report)->toContain('Cockpit Wave 30 — Pay Code Explorer Functional Read Model Parity / Legacy Index Comparison');
});
