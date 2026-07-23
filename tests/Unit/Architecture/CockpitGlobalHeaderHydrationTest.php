<?php

declare(strict_types=1);

it('keeps the shared header read model wired across every cockpit page', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $routes = file_get_contents($packageRoot.'/routes/web.php');
    $middleware = file_get_contents($packageRoot.'/src/Http/Middleware/ShareCockpitHeaderReadModel.php');
    $layout = file_get_contents($packageRoot.'/resources/js/cockpit/layouts/CockpitLayout.vue');
    $types = file_get_contents($packageRoot.'/resources/js/cockpit/types.ts');
    $provider = file_get_contents($packageRoot.'/src/Services/Cockpit/WalletCockpitHeaderReadModelProvider.php');
    $config = file_get_contents($packageRoot.'/config/x-change.php');

    expect($routes)
        ->toContain("Route::prefix('cockpit')->middleware(ShareCockpitHeaderReadModel::class)")
        ->and($middleware)
        ->toContain('Inertia::share(')
        ->toContain("'cockpit_header_read_model'")
        ->toContain('forOperator($request->user())')
        ->and($layout)
        ->toContain('cockpitHeaderReadModel?: CockpitHeaderReadModel')
        ->toContain('props.cockpitHeaderReadModel?.balances')
        ->and($types)
        ->toContain('export type CockpitHeaderReadModel')
        ->toContain('export type CockpitHeaderPageProps');

    foreach ([
        'Dashboard.vue',
        'QuickGenerate.vue',
        'RuntimeProfile.vue',
        'PayCodeExplorer.vue',
        'VoucherDetail.vue',
        'DistributionWorkspace.vue',
    ] as $page) {
        expect(file_get_contents($packageRoot.'/resources/js/cockpit/pages/'.$page))
            ->toContain(':cockpit-header-read-model="props.cockpit_header_read_model"');
    }

    expect($provider)
        ->toContain("config('x-change.cockpit.header_provider_balance.enabled', true)")
        ->toContain('return $this->disconnectedProviderBalance();')
        ->and($config)
        ->toContain("'enabled' => (bool) env('XCHANGE_COCKPIT_HEADER_PROVIDER_BALANCE_ENABLED', true)");
});
