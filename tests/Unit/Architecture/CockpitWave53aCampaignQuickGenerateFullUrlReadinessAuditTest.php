<?php

declare(strict_types=1);

it('documents the campaign quick generate full url readiness audit', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/328-wave-53a-campaign-quick-generate-full-url-readiness-audit.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $controller = file_get_contents($packageRoot.'/src/Http/Controllers/Web/Cockpit/CockpitQuickGenerateMutationRouteShellController.php');
    $linksData = file_get_contents($packageRoot.'/src/Data/PayCodeLinksData.php');

    expect($report)
        ->toContain('Cockpit Wave 53A')
        ->toContain('PayCodeLinksData')
        ->toContain('result.links.redeem')
        ->toContain('result.links.redeem_path')
        ->toContain('Beneficiary Pay Code URL')
        ->toContain('Showing the URL does not authorize')
        ->and($controller)
        ->toContain("'redeem' => \$result->links->redeem")
        ->toContain("'redeem_path' => \$result->links->redeem_path")
        ->and($linksData)
        ->toContain('public string $redeem')
        ->toContain('public string $redeem_path');
});
