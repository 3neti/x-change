<?php

declare(strict_types=1);

it('documents the quick generate full url ui presentation', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/330-wave-53c-quick-generate-full-url-ui-presentation.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $component = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitQuickGenerateFoundation.test.ts');

    expect($report)
        ->toContain('Cockpit Wave 53C')
        ->toContain('Beneficiary Pay Code URL')
        ->toContain('presentation only')
        ->toContain('does not send SMS, email, webhook, or campaign delivery')
        ->and($component)
        ->toContain('beneficiaryRedeemUrl')
        ->toContain('beneficiaryRedeemPath')
        ->toContain('cockpit-quick-generate-beneficiary-url-panel')
        ->toContain('cockpit-quick-generate-beneficiary-url-link')
        ->and($frontendTest)
        ->toContain('https://example.test/r/PC-UI-001')
        ->toContain('https://example.test/r/PC-CAMPAIGN-001')
        ->toContain('cockpit-quick-generate-beneficiary-url-panel');
});
