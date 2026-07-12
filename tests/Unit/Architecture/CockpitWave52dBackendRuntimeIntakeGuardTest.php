<?php

declare(strict_types=1);

it('documents the backend runtime intake guard for campaign quick generate adoption', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/326-wave-52d-backend-runtime-intake-guard.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $featureTest = file_get_contents($packageRoot.'/tests/Feature/Cockpit/CockpitQuickGenerateCampaignRuntimeAdoptionTest.php');

    expect($report)
        ->toContain('Cockpit Wave 52D')
        ->toContain('metadata.campaign.*')
        ->toContain('cash.validation.mobile')
        ->toContain('inputs.fields[] = mobile')
        ->toContain('GeneratePayCode')
        ->toContain('No new visible UI')
        ->and($featureTest)
        ->toContain('preserves campaign context and mobile validation through the quick generate runtime handoff')
        ->toContain("'cash.validation.mobile'")
        ->toContain("'inputs.fields'")
        ->toContain("'metadata.campaign.planning_key'")
        ->toContain('assertJsonMissingPath');
});
