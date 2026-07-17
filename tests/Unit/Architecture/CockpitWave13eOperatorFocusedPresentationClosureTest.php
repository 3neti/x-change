<?php

declare(strict_types=1);

it('documents cockpit wave 13e operator focused presentation closure', function () {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/174-wave-13e-operator-focused-presentation-closure.md');
    $quickGenerate = file_get_contents($packageRoot.'/resources/js/cockpit/pages/QuickGenerate.vue');
    $callout = file_get_contents($packageRoot.'/resources/js/components/x-change/CockpitBridgeCallout.vue');
    $diagnostics = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitDiagnosticsDisclosure.vue');

    expect($report)->toContain('Wave 13A')
        ->and($report)->toContain('Wave 13B')
        ->and($report)->toContain('Wave 13C')
        ->and($report)->toContain('Wave 13D')
        ->and($report)->toContain('php artisan x-change:install --force')
        ->and($report)->toContain('npm run dev')
        ->and($quickGenerate)->toContain('CockpitDiagnosticsDisclosure')
        ->and($quickGenerate)->toContain('Quick Generate Runtime')
        ->and($quickGenerate)->toContain('Architecture history and gate diagnostics')
        ->and($callout)->toContain('Cockpit bridge')
        ->and($diagnostics)->toContain('Show diagnostic history');
});
