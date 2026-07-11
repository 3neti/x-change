<?php

declare(strict_types=1);

it('documents cockpit wave 30a pay code explorer functional parity audit', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/206-wave-30a-pay-code-explorer-functional-parity-audit.md');

    expect($report)->toContain('Cockpit Wave 30A — Legacy Pay Code Index vs Cockpit Explorer Read Model Parity Audit')
        ->and($report)->toContain('PayCodeIndexPageController')
        ->and($report)->toContain('resources/js/pages/x-change/pay-codes/Index.vue')
        ->and($report)->toContain('VoucherLifecycleCockpitReadModelProvider::forPayCodeList()')
        ->and($report)->toContain('search')
        ->and($report)->toContain('status')
        ->and($report)->toContain('read-model stats')
        ->and($report)->toContain('status filter option metadata')
        ->and($report)->toContain('GET navigation, not mutation')
        ->and($report)->toContain('must not')
        ->and($report)->toContain('Cockpit Wave 30B — Pay Code Explorer Filter / Summary Read Model Contract');
});
