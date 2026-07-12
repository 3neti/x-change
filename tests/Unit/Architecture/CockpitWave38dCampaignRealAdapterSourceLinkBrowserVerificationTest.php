<?php

declare(strict_types=1);

it('records the campaign real adapter source link browser verification', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/256-wave-38d-campaign-real-adapter-source-link-ui-browser-verification.md');

    expect($report)
        ->toContain('Cockpit Wave 38D')
        ->toContain('php artisan x-change:install --force')
        ->toContain('php artisan x-change:doctor --assets --json')
        ->toContain('npx playwright test tests/playwright/cockpit-campaign-source-link.spec.ts')
        ->toContain('Open Quick Generate')
        ->toContain('Campaign context prefill')
        ->toContain('asset doctor: passed')
        ->toContain('Playwright: 1 passed')
        ->toContain('Cockpit Wave 38E — Campaign Workspace Entry Point Real Adapter Adoption Closure');
});
