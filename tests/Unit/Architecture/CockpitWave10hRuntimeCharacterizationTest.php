<?php

declare(strict_types=1);

it('documents cockpit wave 10h runtime characterization with existing GeneratePayCode path', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/158-wave-10h-runtime-characterization-with-existing-generate-pay-code-path.md';
    $controllerPath = $packageRoot.'/src/Http/Controllers/Web/Cockpit/CockpitQuickGenerateMutationRouteShellController.php';

    $report = file_get_contents($reportPath);
    $controller = file_get_contents($controllerPath);

    expect($report)->toContain('GeneratePayCode')
        ->and($report)->toContain('CockpitQuickGenerateDraftFactoryContract')
        ->and($report)->toContain('CockpitIssuanceDraftCompilerContract')
        ->and($report)->toContain('EstimatePayCodeCost')
        ->and($report)->toContain('BuildBalanceOverview')
        ->and($report)->toContain('No public Pay Code API route replacement')
        ->and($controller)->toContain('GeneratePayCode $generatePayCode')
        ->and($controller)->toContain('CockpitQuickGenerateDraftFactoryContract $quickGenerateDraftFactory')
        ->and($controller)->toContain('CockpitIssuanceDraftCompilerContract $draftCompiler')
        ->and($controller)->toContain('$generatePayCode->handle($payload)')
        ->and($controller)->not->toContain('GeneratePayCodeController $');
});
