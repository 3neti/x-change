<?php

declare(strict_types=1);

it('records the campaign quick generate source link browser and publish verification', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/251-wave-37d-campaign-quick-generate-source-link-browser-publish-verification.md');

    expect($report)
        ->toContain('Cockpit Wave 37D')
        ->toContain('php artisan x-change:install --force')
        ->toContain('php artisan x-change:doctor --assets --json')
        ->toContain('npx playwright test tests/playwright/cockpit-campaign-source-link.spec.ts')
        ->toContain('Open Quick Generate')
        ->toContain('Campaign context prefill')
        ->toContain('asset doctor: passed')
        ->toContain('Playwright: 1 passed')
        ->toContain('Cockpit Wave 37E — Campaign Context Source Link Generation Closure');
});
