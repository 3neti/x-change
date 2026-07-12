<?php

declare(strict_types=1);

it('documents the campaign template quick generate runtime adoption audit', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/323-wave-52a-campaign-template-quick-generate-runtime-adoption-audit.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $controller = file_get_contents($packageRoot.'/src/Http/Controllers/Web/Cockpit/CockpitQuickGenerateMutationRouteShellController.php');

    expect($report)
        ->toContain('Cockpit Wave 52A')
        ->toContain('CockpitQuickGenerateDraftFactoryContract')
        ->toContain('CockpitIssuanceDraftCompilerContract')
        ->toContain('GeneratePayCodeRequest')
        ->toContain('GeneratePayCode')
        ->toContain('metadata.campaign')
        ->toContain('should adopt the campaign/template bridge through the existing Quick Generate payload path')
        ->toContain('No campaign mutation')
        ->and($controller)
        ->toContain('CockpitQuickGenerateDraftFactoryContract')
        ->toContain('CockpitIssuanceDraftCompilerContract')
        ->toContain('GeneratePayCode')
        ->toContain('$quickGenerateDraftFactory->fromPayload')
        ->toContain('$draftCompiler->compile');
});
