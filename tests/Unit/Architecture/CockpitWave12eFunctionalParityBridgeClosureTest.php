<?php

declare(strict_types=1);

it('documents cockpit wave 12e functional parity bridge closure', function () {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/169-wave-12e-functional-parity-bridge-closure-host-publish-handoff.md');
    $quickGenerate = file_get_contents($packageRoot.'/resources/js/cockpit/pages/QuickGenerate.vue');
    $payCodeCreate = file_get_contents($packageRoot.'/src/Http/Controllers/Web/PayCodeCreatePageController.php');
    $payCodeIndex = file_get_contents($packageRoot.'/src/Http/Controllers/Web/PayCodeIndexPageController.php');
    $balances = file_get_contents($packageRoot.'/src/Http/Controllers/Web/BalancePageController.php');

    expect($report)->toContain('Cockpit Wave 12A')
        ->and($report)->toContain('Cockpit Wave 12B')
        ->and($report)->toContain('Cockpit Wave 12C')
        ->and($report)->toContain('Cockpit Wave 12D')
        ->and($report)->toContain('php artisan x-change:install --force')
        ->and($report)->toContain('npm run dev')
        ->and($report)->toContain('Wave 13')
        ->and($quickGenerate)->toContain('Quick Generate Runtime')
        ->and($quickGenerate)->not->toContain('No voucher generation')
        ->and($payCodeCreate)->toContain('x-change.pay-code-create.cockpit-bridge.v1')
        ->and($payCodeIndex)->toContain('x-change.pay-code-index.cockpit-bridge.v1')
        ->and($balances)->toContain('x-change.balances.cockpit-bridge.v1');
});
