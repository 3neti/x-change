<?php

declare(strict_types=1);

it('documents provider failure recovery without automatic payout retry', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $runbook = file_get_contents($packageRoot.'/docs/claim-ux/provider-failure-and-pay-code-recovery-runbook.md');

    expect($runbook)
        ->toContain('Do not automatically retry the same payout from the public claim experience.')
        ->toContain('php artisan xchange:disbursement:check {PAY_CODE} --json')
        ->toContain('operator_guidance.action')
        ->toContain('Provider dashboard/API says `REJECTED`')
        ->toContain('/x/claim/{PAY_CODE}')
        ->toContain('replacement Pay Code recovery, not')
        ->toContain('same-voucher payout retry');
});
