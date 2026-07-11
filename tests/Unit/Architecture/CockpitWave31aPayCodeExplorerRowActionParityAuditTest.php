<?php

declare(strict_types=1);

it('documents cockpit wave 31a pay code explorer row action parity audit', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/213-wave-31a-pay-code-explorer-row-action-parity-audit.md');

    expect($report)->toContain('Cockpit Wave 31A — Pay Code Explorer Detail Navigation / Row Action Parity Audit')
        ->and($report)->toContain('/x/cockpit/pay-codes/{code}')
        ->and($report)->toContain('/x/cockpit/pay-codes/{code}/distribution')
        ->and($report)->toContain('voucher mutation')
        ->and($report)->toContain('Cockpit Wave 31B — Pay Code Explorer Row Action Read Model Contract');
});
