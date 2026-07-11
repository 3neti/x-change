<?php

declare(strict_types=1);

it('documents cockpit wave 12b legacy page functional parity bridge audit', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/166-wave-12b-legacy-page-functional-parity-bridge-audit.md');
    $webRoutes = file_get_contents($packageRoot.'/routes/web.php');
    $payCodeCreate = file_get_contents($packageRoot.'/src/Http/Controllers/Web/PayCodeCreatePageController.php');
    $balances = file_get_contents($packageRoot.'/src/Http/Controllers/Web/BalancePageController.php');

    expect($report)->toContain('/x/pay-codes/create')
        ->and($report)->toContain('/x/pay-codes')
        ->and($report)->toContain('/x/balances')
        ->and($report)->toContain('PayCodeCreatePageController')
        ->and($report)->toContain('PayCodeIndexPageController')
        ->and($report)->toContain('BalancePageController')
        ->and($report)->toContain('bridge, not replace')
        ->and($webRoutes)->toContain("name('x-change.pay-codes.create')")
        ->and($webRoutes)->toContain("name('x-change.pay-codes.index')")
        ->and($webRoutes)->toContain("name('x-change.balances.index')")
        ->and($payCodeCreate)->toContain('BuildBalanceOverview')
        ->and($balances)->toContain('BuildBalanceOverview');
});
