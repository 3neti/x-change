<?php

declare(strict_types=1);

it('records the quick generate post issuance navigation and share handoff scope', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/231-wave-34a-quick-generate-post-issuance-navigation-share-handoff-audit.md');

    expect($report)
        ->toContain('Cockpit Wave 34A')
        ->toContain('Open Cockpit detail')
        ->toContain('Distribution / share workspace')
        ->toContain('Wave 34B — Post-Issuance Navigation Read Model Contract')
        ->toContain('must not')
        ->toContain('auto-redirect')
        ->toContain('dispatch SMS, email, webhook, or in-app feedback')
        ->toContain('generate QR codes or short links')
        ->toContain('mutate vouchers beyond the existing `GeneratePayCode` issuance handoff')
        ->toContain('move money outside the existing issuance path')
        ->not->toContain('dispatch feedback after success')
        ->not->toContain('automatically redirect after success');
});
