<?php

declare(strict_types=1);

it('documents cockpit wave 54c pay code detail full url presentation', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/334-wave-54c-pay-code-detail-full-url-presentation.md';
    $pagePath = $packageRoot.'/resources/js/cockpit/pages/VoucherDetail.vue';
    $shareCardPath = $packageRoot.'/resources/js/cockpit/components/CockpitPayCodeShareCard.vue';
    $frontendTestPath = $packageRoot.'/tests/frontend/cockpit/CockpitPayCodeShareCard.test.ts';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $page = file_get_contents($pagePath);
    $shareCard = file_get_contents($shareCardPath);
    $frontendTest = file_get_contents($frontendTestPath);

    expect($report)->toContain('Cockpit Wave 54C — Pay Code Detail Full URL Presentation')
        ->and($report)->toContain('Beneficiary Pay Code URL')
        ->and($report)->toContain('delivery disabled')
        ->and($report)->toContain('Cockpit Wave 54D — Distribution Workspace Full URL Presentation')
        ->and($page)->toContain(':claim-url="beneficiaryRedeemUrl ?? beneficiaryRedeemPath"')
        ->and($shareCard)->toContain('cockpit-pay-code-share-url-link')
        ->and($shareCard)->toContain('normalizedClaimUrl')
        ->and($frontendTest)->toContain('full canonical URL in the prominent variant')
        ->and($frontendTest)->toContain('https://example.test/x/claim/PC-SHARE-001');
});
