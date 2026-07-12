<?php

declare(strict_types=1);

it('documents the campaign recipient submission attribution audit', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/273-wave-42a-campaign-recipient-submission-attribution-audit.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 42A')
        ->toContain('recipient-level campaign attribution')
        ->toContain('campaign_read_model.recipient_quick_generate_links')
        ->toContain('GeneratePayCode')
        ->toContain('Recipient attribution is read-only result evidence')
        ->toContain('Cockpit Wave 42B — Campaign Recipient Attribution Response Contract');

    expect($cockpitCompass)
        ->toContain('Cockpit Wave 41 next recommended wave: `Cockpit Wave 42 — Campaign Recipient Quick Generate Submission Attribution / Result Closure`')
        ->toContain('Cockpit Wave 42A result: Campaign Recipient Submission Attribution audit completed');

    expect($settlementCompass)
        ->toContain('Cockpit Wave 42 — Campaign Recipient Quick Generate Submission Attribution / Result Closure')
        ->toContain('Cockpit Wave 42A — Campaign Recipient Submission Attribution Audit');
});
