<?php

declare(strict_types=1);

it('documents the campaign recipient detail context rendering', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/295-wave-46c-campaign-recipient-detail-context-rendering.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/VoucherDetail.vue');
    $frontend = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitVoucherDetailFoundation.test.ts');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 46C')
        ->toContain('Campaign recipient context')
        ->toContain('pay_code_detail')
        ->toContain('Cockpit Wave 46D — Campaign Recipient Distribution Context Rendering');

    expect($page)
        ->toContain('cockpit-voucher-detail-campaign-navigation-context')
        ->toContain('Read-only Pay Code detail context')
        ->toContain("destination !== 'pay_code_detail'");

    expect($frontend)
        ->toContain('renders safe campaign recipient context on voucher detail without mutation controls')
        ->toContain('must-not-render')
        ->toContain('does not render campaign context on voucher detail for the wrong destination');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 46C result: Campaign Recipient Detail context rendering completed');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 46C — Campaign Recipient Detail Context Rendering');
});
