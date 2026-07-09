<?php

declare(strict_types=1);

it('documents the quick generate mutation plan without implementing runtime behavior', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/042-quick-generate-mutation-plan-safety-contract.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');
    $webRoutes = file_get_contents($packageRoot.'/routes/web.php');

    expect($report)->toContain('Cockpit Mutation Wave 1 — Quick Generate Draft-to-Issuance Boundary Plan')
        ->and($report)->toContain('Status: Plan drafted; no implementation authorized in this slice')
        ->and($report)->toContain('GeneratePayCodeRequest')
        ->and($report)->toContain('GeneratePayCodeController')
        ->and($report)->toContain('GeneratePayCode')
        ->and($report)->toContain('Cockpit Mutation Wave 1A — Quick Generate Mutation Contract and Safety Gates')
        ->and($report)->toContain('Wave 1B — Mutation Route Shell')
        ->and($report)->toContain('Wave 1E — UI Submit Enablement')
        ->and($report)->toContain('This planning slice does not add:')
        ->and($report)->toContain('Cockpit `POST`, `PUT`, `PATCH`, or `DELETE` routes')
        ->and($report)->toContain('money movement')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 1 — Quick Generate Draft-to-Issuance Boundary Plan')
        ->and($cockpitCompass)->toContain('no mutation route or runtime behavior implemented')
        ->and($cockpitCompass)->toContain('reports/042-quick-generate-mutation-plan-safety-contract.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 1 — Quick Generate Draft-to-Issuance Boundary Plan')
        ->and($settlementCompass)->toContain('first Quick Generate mutation implementation plan drafted without runtime changes')
        ->and($webRoutes)->not->toContain("Route::post('quick-generate'")
        ->and($webRoutes)->not->toContain('quick-generate.store');
});
