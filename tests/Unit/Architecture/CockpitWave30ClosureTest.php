<?php

declare(strict_types=1);

it('documents cockpit wave 30 functional parity closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/212-wave-30-pay-code-explorer-functional-parity-closure.md');

    expect($report)->toContain('Cockpit Wave 30 — Pay Code Explorer Functional Read Model Parity Closure')
        ->and($report)->toContain('reports/206-wave-30a-pay-code-explorer-functional-parity-audit.md')
        ->and($report)->toContain('reports/207-wave-30b-pay-code-explorer-filter-summary-read-model-contract.md')
        ->and($report)->toContain('reports/208-wave-30c-pay-code-explorer-provider-filtering-stats-parity.md')
        ->and($report)->toContain('reports/209-wave-30d-pay-code-explorer-controller-query-intake.md')
        ->and($report)->toContain('reports/210-wave-30e-pay-code-explorer-filter-ui-presentation.md')
        ->and($report)->toContain('reports/211-wave-30f-pay-code-explorer-filter-browser-publish-verification.md')
        ->and($report)->toContain('GET /x/cockpit/pay-codes?search={term}&status={status}')
        ->and($report)->toContain('ChromeDriver')
        ->and($report)->toContain('did not')
        ->and($report)->toContain('Cockpit Wave 31 — Pay Code Explorer Detail Navigation / Row Action Runtime Parity');
});
