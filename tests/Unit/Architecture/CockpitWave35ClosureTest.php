<?php

declare(strict_types=1);

it('closes the campaign context quick generate adoption wave', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/242-wave-35-campaign-context-quick-generate-adoption-closure.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Cockpit Wave 35')
        ->toContain('Wave 35A')
        ->toContain('Wave 35B')
        ->toContain('Wave 35C')
        ->toContain('Wave 35D')
        ->toContain('Wave 35E')
        ->toContain('Campaign context does not mutate campaign state')
        ->toContain('does not bypass `GeneratePayCode`')
        ->toContain('Cockpit Wave 36 — Campaign-Sourced Quick Generate Result Attribution / Explorer Bridge');

    expect($cockpitCompass)
        ->toContain('reports/242-wave-35-campaign-context-quick-generate-adoption-closure.md')
        ->toContain('Cockpit Wave 36 — Campaign-Sourced Quick Generate Result Attribution / Explorer Bridge');

    expect($settlementCompass)
        ->toContain('../ui-cockpit/reports/242-wave-35-campaign-context-quick-generate-adoption-closure.md')
        ->toContain('Cockpit Wave 36 — Campaign-Sourced Quick Generate Result Attribution / Explorer Bridge');
});
