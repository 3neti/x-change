<?php

declare(strict_types=1);

it('records the campaign context quick generate browser and publish verification', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/241-wave-35e-campaign-context-quick-generate-browser-publish-verification.md');
    $spec = file_get_contents(dirname($packageRoot, 2).'/tests/playwright/cockpit-quick-generate-campaign-context.spec.ts');

    expect($report)
        ->toContain('Cockpit Wave 35E')
        ->toContain('php artisan x-change:install --force')
        ->toContain('php artisan x-change:doctor --assets --json')
        ->toContain('npx playwright test tests/playwright/cockpit-quick-generate-campaign-context.spec.ts')
        ->toContain('Asset doctor: checked 58, ok 58, stale 0, missing 0, extra 0')
        ->toContain('Playwright: 1 passed')
        ->toContain('Intercepts only the POST')
        ->toContain('No real campaign mutation')
        ->toContain('Cockpit Wave 35F — Campaign Context Quick Generate Adoption Closure');

    expect($spec)
        ->toContain("route.request().method() !== 'POST'")
        ->toContain('cockpit-quick-generate-campaign-context-panel')
        ->toContain('plan-playwright-35')
        ->toContain('metadata')
        ->toContain('mutates_campaign: false')
        ->toContain('campaign_payload')
        ->toContain('provider_payload')
        ->toContain('wallet');
});
