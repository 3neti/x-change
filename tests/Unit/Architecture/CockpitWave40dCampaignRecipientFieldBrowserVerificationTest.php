<?php

declare(strict_types=1);

it('records the campaign recipient field browser and published asset verification', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/266-wave-40d-campaign-recipient-field-browser-published-asset-verification.md');

    expect($report)
        ->toContain('Cockpit Wave 40D')
        ->toContain('php artisan x-change:install --force')
        ->toContain('php artisan x-change:doctor --assets --json')
        ->toContain('npx playwright test tests/playwright/cockpit-campaign-source-link.spec.ts')
        ->toContain('Open Quick Generate')
        ->toContain('Campaign context prefill')
        ->toContain('asset doctor: passed')
        ->toContain('Playwright: 1 passed')
        ->toContain('Cockpit Wave 40E — Campaign Recipient-to-Issuance Draft Field Mapping Closure');
});
