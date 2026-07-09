<?php

declare(strict_types=1);

it('documents the quick generate mutation route shell with runtime disabled', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/044-quick-generate-mutation-route-shell.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');
    $controller = file_get_contents($packageRoot.'/src/Http/Controllers/Web/Cockpit/CockpitQuickGenerateMutationRouteShellController.php');

    expect($report)->toContain('Cockpit Mutation Wave 1B — Quick Generate Mutation Route Shell')
        ->and($report)->toContain('Route shell scaffolded; mutation runtime remains disabled')
        ->and($report)->toContain('x-change.cockpit.quick-generate.store')
        ->and($report)->toContain('does not call `GeneratePayCode`')
        ->and($report)->toContain('money movement')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 1B — Quick Generate Mutation Route Shell')
        ->and($cockpitCompass)->toContain('reports/044-quick-generate-mutation-route-shell.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 1B — Quick Generate Mutation Route Shell')
        ->and($settlementCompass)->toContain('Route shell returns a disabled-runtime response')
        ->and($controller)->not->toContain('use LBHurtado\\XChange\\Actions\\PayCode\\GeneratePayCode')
        ->and($controller)->not->toContain('use LBHurtado\\XChange\\Http\\Requests\\GeneratePayCodeRequest');
});
