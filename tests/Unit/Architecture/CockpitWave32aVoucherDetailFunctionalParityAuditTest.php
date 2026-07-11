<?php

declare(strict_types=1);

it('documents cockpit wave 32a voucher detail functional parity audit', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/219-wave-32a-voucher-detail-functional-parity-audit.md');

    expect($report)->toContain('Cockpit Wave 32A — Voucher Detail Functional Parity Audit')
        ->and($report)->toContain('/x/cockpit/pay-codes/{code}')
        ->and($report)->toContain('lifecycle summary facts')
        ->and($report)->toContain('claim/approval evidence status')
        ->and($report)->toContain('Cockpit Wave 32B — Voucher Detail Evidence Summary Read Model Contract')
        ->and($report)->toContain('must not add')
        ->and($report)->toContain('execution-driver invocation');
});
