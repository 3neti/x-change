<?php

declare(strict_types=1);

it('documents cockpit wave 29a pay code explorer runtime parity audit', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/204-wave-29a-pay-code-explorer-runtime-parity-audit.md');

    expect($report)->toContain('GET /x/cockpit/pay-codes')
        ->and($report)->toContain('resources/js/pages/x-change/pay-codes/Index.vue')
        ->and($report)->toContain('CockpitPayCodeListReadModelData')
        ->and($report)->toContain('/x/cockpit/pay-codes?activity_code={code}&activity_source=operator_issuance_activity')
        ->and($report)->toContain('Open in Explorer')
        ->and($report)->toContain('does not mutate vouchers')
        ->and($report)->toContain('raw payload display')
        ->and($report)->toContain('Cockpit Wave 29B — Pay Code Explorer Activity Query Intake');
});
