<?php

declare(strict_types=1);

it('documents cockpit wave 14b host mirror publish state record', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/176-wave-14b-host-mirror-publish-state-record.md');

    expect($report)->toContain('resources/js/cockpit/components/CockpitDiagnosticsDisclosure.vue')
        ->and($report)->toContain('resources/js/cockpit/pages/QuickGenerate.vue')
        ->and($report)->toContain('resources/js/cockpit/quickGenerateDefaults.ts')
        ->and($report)->toContain('resources/js/cockpit/types.ts')
        ->and($report)->toContain('host-published mirrors')
        ->and($report)->toContain('package source remains authoritative');
});
