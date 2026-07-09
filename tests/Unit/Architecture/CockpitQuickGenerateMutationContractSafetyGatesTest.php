<?php

declare(strict_types=1);

it('documents the quick generate mutation contract safety gates without runtime enablement', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/043-quick-generate-mutation-contract-safety-gates.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');
    $webRoutes = file_get_contents($packageRoot.'/routes/web.php');

    expect($report)->toContain('Cockpit Mutation Wave 1A — Quick Generate Mutation Contract and Safety Gates')
        ->and($report)->toContain('Status: Contract scaffolded; mutation runtime remains disabled')
        ->and($report)->toContain('x-change.cockpit.quick-generate-mutation.v1')
        ->and($report)->toContain('x-change.cockpit.quick-generate.store')
        ->and($report)->toContain('GeneratePayCodeRequest-compatible-adapter')
        ->and($report)->toContain('GeneratePayCode')
        ->and($report)->toContain('Runtime Disabled')
        ->and($report)->toContain('Cockpit `POST`, `PUT`, `PATCH`, or `DELETE` routes')
        ->and($report)->toContain('money movement')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 1A — Quick Generate Mutation Contract and Safety Gates')
        ->and($cockpitCompass)->toContain('Contract scaffolded; mutation runtime remains disabled')
        ->and($cockpitCompass)->toContain('reports/043-quick-generate-mutation-contract-safety-gates.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 1A — Quick Generate Mutation Contract and Safety Gates')
        ->and($settlementCompass)->toContain('Runtime remains disabled and allowed Cockpit methods remain `GET` only')
        ->and($webRoutes)->not->toContain("Route::post('quick-generate'")
        ->and($webRoutes)->not->toContain('quick-generate.store');
});
