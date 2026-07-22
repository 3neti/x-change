<?php

declare(strict_types=1);

it('documents runtime profile compact diagnostics slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/639-runtime-profile-compact-diagnostics-slice-1.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/RuntimeProfile.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitRuntimeProfileDiagnostics.test.ts');
    $compass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Runtime Profile Compact Diagnostics — Slice 1')
        ->toContain('slim operational header')
        ->toContain('Presentation-only runtime-profile shell compression')
        ->and($page)->toContain('data-testid="cockpit-runtime-profile-header"')
        ->and($page)->toContain('data-testid="cockpit-runtime-profile-header-facts"')
        ->and($page)->toContain('Runtime profile context')
        ->and($frontendTest)->toContain("expect(header.classes()).toContain('py-3')")
        ->and($frontendTest)->toContain("expect(context.attributes('open')).toBeUndefined()")
        ->and($compass)->toContain('Runtime Profile Compact Diagnostics — Slice 1')
        ->and($settlementCompass)->toContain('Runtime Profile Compact Diagnostics — Slice 1');
});
