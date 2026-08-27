<?php

declare(strict_types=1);

it('documents cockpit wave 55c voucher detail manual copy adoption', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/339-wave-55c-voucher-detail-manual-copy-adoption.md';
    $shareCardPath = $packageRoot.'/resources/js/cockpit/components/CockpitPayCodeShareCard.vue';
    $frontendTestPath = $packageRoot.'/tests/frontend/cockpit/CockpitPayCodeShareCard.test.ts';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $shareCard = file_get_contents($shareCardPath);
    $frontendTest = file_get_contents($frontendTestPath);

    expect($report)->toContain('Cockpit Wave 55C — Voucher Detail Manual Copy Adoption')
        ->and($report)->toContain('does not call `fetch`')
        ->and($report)->toContain('Cockpit Wave 55D — Distribution Workspace Manual Copy Adoption')
        ->and($shareCard)->toContain('cockpit-pay-code-share-copy')
        ->and($shareCard)->toContain('copyClaimLink')
        ->and($frontendTest)->toContain('copies only the canonical claim URL')
        ->and($frontendTest)->toContain('writeText');
});
