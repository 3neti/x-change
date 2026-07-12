<?php

declare(strict_types=1);

it('documents the campaign recipient activity metadata handoff', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/279-wave-43b-campaign-recipient-activity-metadata-handoff.md');
    $controller = file_get_contents($packageRoot.'/src/Http/Controllers/Web/Cockpit/CockpitQuickGenerateMutationRouteShellController.php');
    $featureTest = file_get_contents($packageRoot.'/tests/Feature/Cockpit/CockpitQuickGenerateCombinedRuntimeTest.php');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 43B')
        ->toContain('campaign_attribution')
        ->toContain('read-only')
        ->toContain('No campaign mutation')
        ->toContain('Cockpit Wave 43C — Campaign Recipient Activity Dashboard Presentation');

    expect($controller)
        ->toContain('operatorSafeCampaignAttributionForActivity')
        ->toContain('campaign_attribution')
        ->toContain("'mutates_campaign' => false");

    expect($featureTest)
        ->toContain('records campaign recipient attribution in durable operator issuance activity metadata')
        ->toContain('recipient-wave-43b')
        ->toContain('campaign-attribution-only');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 43B result: Campaign Recipient Activity metadata handoff completed');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 43B — Campaign Recipient Activity Metadata Handoff');
});
