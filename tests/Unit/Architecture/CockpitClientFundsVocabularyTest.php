<?php

declare(strict_types=1);

it('keeps the compatibility key while removing internal balance from operator copy', function () {
    $packageRoot = dirname(__DIR__, 3);
    $operatorSources = [
        $packageRoot.'/config/x-change.php',
        $packageRoot.'/src/Services/Cockpit/WalletCockpitHeaderReadModelProvider.php',
        $packageRoot.'/src/Services/Cockpit/VoucherLifecycleCockpitReadModelProvider.php',
        $packageRoot.'/resources/js/cockpit/components/CockpitGlobalHeader.vue',
        $packageRoot.'/resources/js/cockpit/components/CockpitLiquidityHero.vue',
        $packageRoot.'/resources/js/cockpit/dashboardDefaults.ts',
        $packageRoot.'/resources/js/cockpit/pages/Dashboard.vue',
        $packageRoot.'/resources/js/cockpit/pages/Funding.vue',
        $packageRoot.'/resources/js/cockpit/pages/QuickGenerate.vue',
    ];
    $operatorCopy = '';

    foreach ($operatorSources as $source) {
        $operatorCopy .= file_get_contents($source);

        expect(file_get_contents($source))
            ->not->toContain('Internal Balance')
            ->not->toContain('Internal balance');
    }

    expect($operatorCopy)
        ->toContain('Client Funds')
        ->and(file_get_contents($packageRoot.'/config/x-change.php'))
        ->toContain("'internal_balance' => [")
        ->toContain("'label' => 'Client Funds'");
});
