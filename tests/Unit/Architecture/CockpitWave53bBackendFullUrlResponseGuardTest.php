<?php

declare(strict_types=1);

it('documents the backend full url response guard', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/329-wave-53b-backend-full-url-response-guard.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $featureTest = file_get_contents($packageRoot.'/tests/Feature/Cockpit/CockpitQuickGenerateCampaignRuntimeAdoptionTest.php');

    expect($report)
        ->toContain('Cockpit Wave 53B')
        ->toContain('result.links.redeem')
        ->toContain('result.links.redeem_path')
        ->toContain('result.links.cockpit_detail')
        ->toContain('result.links.cockpit_distribution')
        ->toContain('feedback delivery payloads')
        ->and($featureTest)
        ->toContain("assertJsonPath('result.links.redeem'")
        ->toContain("assertJsonPath('result.links.redeem_path'")
        ->toContain("assertJsonMissingPath('delivery_payload'")
        ->toContain("assertJsonMissingPath('feedback_delivery'");
});
