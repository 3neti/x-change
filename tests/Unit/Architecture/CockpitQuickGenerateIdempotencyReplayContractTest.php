<?php

declare(strict_types=1);

it('documents the quick generate idempotency and replay contract boundary', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/046-quick-generate-idempotency-replay-contract.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');
    $controller = file_get_contents($packageRoot.'/src/Http/Controllers/Web/Cockpit/CockpitQuickGenerateMutationRouteShellController.php');

    expect($report)->toContain('Cockpit Mutation Wave 1D — Idempotency and Replay Contract')
        ->and($report)->toContain('IdempotencyService')
        ->and($report)->toContain('200 OK')
        ->and($report)->toContain('409 Conflict')
        ->and($report)->toContain('Cockpit Mutation Wave 1E — UI Submit Enablement')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 1D — Idempotency and Replay Contract')
        ->and($cockpitCompass)->toContain('reports/046-quick-generate-idempotency-replay-contract.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 1D — Idempotency and Replay Contract')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/046-quick-generate-idempotency-replay-contract.md')
        ->and($controller)->toContain('use LBHurtado\\XChange\\Services\\IdempotencyService;')
        ->and($controller)->toContain('recallOrValidate')
        ->and($controller)->toContain('remember')
        ->and($controller)->toContain('operator-safe-generated-facts-only');
});
