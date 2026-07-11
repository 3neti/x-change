<?php

declare(strict_types=1);

it('records the quick generate post issuance browser and publish verification', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/235-wave-34e-quick-generate-post-issuance-browser-publish-verification.md');
    $spec = file_get_contents(dirname($packageRoot, 2).'/tests/playwright/cockpit-quick-generate-post-issuance.spec.ts');

    expect($report)
        ->toContain('Cockpit Wave 34E')
        ->toContain('php artisan x-change:doctor --assets --json')
        ->toContain('npx playwright test tests/playwright/cockpit-quick-generate-post-issuance.spec.ts')
        ->toContain('intercepts only the POST')
        ->toContain('No real issuance was performed')
        ->toContain('Cockpit Wave 34F — Post-Issuance Navigation Closure');

    expect($spec)
        ->toContain("route.request().method() !== 'POST'")
        ->toContain('PC-PLAYWRIGHT-34')
        ->toContain('cockpit-quick-generate-post-issuance-link-detail')
        ->toContain('cockpit-quick-generate-post-issuance-link-distribution')
        ->toContain('provider_payload')
        ->toContain('raw_payload')
        ->toContain('wallet');
});
