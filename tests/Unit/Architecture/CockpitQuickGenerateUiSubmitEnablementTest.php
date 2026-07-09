<?php

it('documents and protects the quick generate ui submit enablement boundary', function () {
    $packageRoot = dirname(__DIR__, 3);

    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/047-quick-generate-ui-submit-enablement.md';
    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');
    $component = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/QuickGenerate.vue');
    $provider = file_get_contents($packageRoot.'/src/Services/Cockpit/VoucherLifecycleCockpitReadModelProvider.php');

    expect($report)->toContain('Cockpit Mutation Wave 1E')
        ->and($report)->toContain('Idempotency-Key')
        ->and($report)->toContain('GeneratePayCodeRequest')
        ->and($report)->toContain('Read Model Refresh / Navigation Closure')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 1E — UI Submit Enablement')
        ->and($cockpitCompass)->toContain('reports/047-quick-generate-ui-submit-enablement.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 1E — UI Submit Enablement')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/047-quick-generate-ui-submit-enablement.md')
        ->and($component)->toContain('data-testid="cockpit-quick-generate-submit-panel"')
        ->and($component)->toContain("'Idempotency-Key'")
        ->and($component)->toContain('processing.value')
        ->and($component)->toContain('metadata')
        ->and($page)->toContain('CockpitQuickGenerateSubmitPanel')
        ->and($provider)->toContain("route_url: Route::has('x-change.cockpit.quick-generate.store')");
});
