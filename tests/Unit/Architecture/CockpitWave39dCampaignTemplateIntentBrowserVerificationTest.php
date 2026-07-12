<?php

declare(strict_types=1);

it('records the campaign template intent browser and published asset verification', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/261-wave-39d-campaign-template-intent-browser-published-asset-verification.md');

    expect($report)
        ->toContain('Cockpit Wave 39D')
        ->toContain('php artisan x-change:install --force')
        ->toContain('php artisan x-change:doctor --assets --json')
        ->toContain('npx playwright test tests/playwright/cockpit-campaign-source-link.spec.ts')
        ->toContain('Open Quick Generate')
        ->toContain('Campaign context prefill')
        ->toContain('asset doctor: passed')
        ->toContain('Playwright: 1 passed')
        ->toContain('Cockpit Wave 39E — Campaign Plan-to-Issuance Draft Template Mapping Closure');
});
