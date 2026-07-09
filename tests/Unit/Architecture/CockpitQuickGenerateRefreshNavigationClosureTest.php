<?php

it('documents and protects quick generate refresh and navigation closure', function () {
    $packageRoot = dirname(__DIR__, 3);

    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/048-quick-generate-read-model-refresh-navigation-closure.md';
    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');
    $controller = file_get_contents($packageRoot.'/src/Http/Controllers/Web/Cockpit/CockpitQuickGenerateMutationRouteShellController.php');
    $component = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue');

    expect($report)->toContain('Cockpit Mutation Wave 1F')
        ->and($report)->toContain('result.links.cockpit_detail')
        ->and($report)->toContain("only: ['quick_generate_read_model']")
        ->and($report)->toContain('automatic redirect')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 1F — Read Model Refresh / Navigation Closure')
        ->and($cockpitCompass)->toContain('reports/048-quick-generate-read-model-refresh-navigation-closure.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 1F — Read Model Refresh / Navigation Closure')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/048-quick-generate-read-model-refresh-navigation-closure.md')
        ->and($controller)->toContain("'cockpit_detail' => Route::has('x-change.cockpit.pay-codes.show')")
        ->and($component)->toContain('data-testid="cockpit-quick-generate-result-link"')
        ->and($component)->toContain('data-testid="cockpit-quick-generate-refresh-button"')
        ->and($component)->toContain("only: ['quick_generate_read_model']")
        ->and($component)->toContain('No automatic redirect is performed');
});
