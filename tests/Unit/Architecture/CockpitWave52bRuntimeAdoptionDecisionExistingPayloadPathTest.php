<?php

declare(strict_types=1);

it('documents the existing payload path as the campaign quick generate runtime adoption decision', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/324-wave-52b-runtime-adoption-decision-existing-payload-path.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $controller = file_get_contents($packageRoot.'/src/Http/Controllers/Web/Cockpit/CockpitQuickGenerateMutationRouteShellController.php');

    expect($report)
        ->toContain('Campaign Template Quick Generate runtime adoption will use the existing Quick Generate mutation payload path')
        ->toContain('GeneratePayCodeRequest-compatible payload')
        ->toContain('CockpitQuickGenerateDraftFactoryContract')
        ->toContain('CockpitIssuanceDraftValidatorContract')
        ->toContain('CockpitIssuanceDraftCompilerContract')
        ->toContain('GeneratePayCode')
        ->toContain('The campaign draft adapter remains a source-link/read-model preparation seam')
        ->toContain('must not')
        ->toContain('call `x-campaign`')
        ->toContain('mutate campaign state')
        ->toContain('cash.validation.mobile')
        ->and($controller)
        ->toContain('GeneratePayCodeRequest $request')
        ->toContain('$quickGenerateDraftFactory->fromPayload')
        ->toContain('$draftCompiler->compile')
        ->not->toContain('CockpitCampaignIssuanceDraftAdapterContract');
});
