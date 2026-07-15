<?php

declare(strict_types=1);

it('documents quick generate productization wave closure', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/405-quick-generate-productization-wave-closure.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Quick Generate Productization Slice 6')
        ->toContain('The current Quick Generate Productization Wave is closed')
        ->toContain('Generation complete')
        ->toContain('Open claim URL')
        ->toContain('Inspect Pay Code')
        ->toContain('journal/action/feedback handoff status')
        ->toContain('No further Quick Generate productization work should proceed implicitly')
        ->toContain('Quick Generate Manual Browser Acceptance / Visual Feedback Intake');

    expect($cockpitCompass)
        ->toContain('Quick Generate Productization Slice 6')
        ->toContain('current Quick Generate Productization Wave is closed');

    expect($settlementCompass)
        ->toContain('Quick Generate Productization Slice 6')
        ->toContain('No further Quick Generate productization work should proceed implicitly');
});
