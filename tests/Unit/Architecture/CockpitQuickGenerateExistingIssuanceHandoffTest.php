<?php

declare(strict_types=1);

it('documents the quick generate existing issuance handoff boundary', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/045-quick-generate-existing-issuance-handoff.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');
    $controller = file_get_contents($packageRoot.'/src/Http/Controllers/Web/Cockpit/CockpitQuickGenerateMutationRouteShellController.php');

    expect($report)->toContain('Cockpit Mutation Wave 1C — Existing Issuance Handoff')
        ->and($report)->toContain('GeneratePayCodeRequest')
        ->and($report)->toContain('GeneratePayCode')
        ->and($report)->toContain('The Cockpit route does not call `GeneratePayCodeController`.')
        ->and($report)->toContain('Cockpit Mutation Wave 1D — Idempotency and Replay Contract')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 1C — Existing Issuance Handoff')
        ->and($cockpitCompass)->toContain('reports/045-quick-generate-existing-issuance-handoff.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 1C — Existing Issuance Handoff')
        ->and($settlementCompass)->toContain('Approve or revise Cockpit Mutation Wave 1D — Idempotency and Replay Contract')
        ->and($controller)->toContain('use LBHurtado\\XChange\\Actions\\PayCode\\GeneratePayCode;')
        ->and($controller)->toContain('use LBHurtado\\XChange\\Http\\Requests\\GeneratePayCodeRequest;')
        ->and($controller)->toContain('operator-safe-generated-facts-only')
        ->and($controller)->not->toContain('GeneratePayCodeController::class');
});
