<?php

declare(strict_types=1);

it('records the campaign attribution browser and publish verification', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/246-wave-36d-campaign-attribution-browser-publish-verification.md');
    $spec = file_get_contents(dirname($packageRoot, 2).'/tests/playwright/cockpit-quick-generate-campaign-context.spec.ts');

    expect($report)
        ->toContain('Cockpit Wave 36D')
        ->toContain('php artisan x-change:install --force')
        ->toContain('php artisan x-change:doctor --assets --json')
        ->toContain('npx playwright test tests/playwright/cockpit-quick-generate-campaign-context.spec.ts')
        ->toContain('Asset doctor: checked 58, ok 58, stale 0, missing 0, extra 0')
        ->toContain('Playwright: 1 passed')
        ->toContain('campaign attribution result card')
        ->toContain('Return to Campaign Explorer')
        ->toContain('Return to Campaign Dashboard')
        ->toContain('Cockpit Wave 36E — Campaign-Sourced Result Attribution Closure');

    expect($spec)
        ->toContain('cockpit-quick-generate-campaign-attribution-panel')
        ->toContain('cockpit-quick-generate-post-issuance-link-campaign_explorer')
        ->toContain('cockpit-quick-generate-post-issuance-link-campaign_dashboard')
        ->toContain('plan-playwright-35')
        ->toContain('mutates_campaign: false')
        ->toContain('campaign_payload')
        ->toContain('provider_payload')
        ->toContain('wallet');
});
