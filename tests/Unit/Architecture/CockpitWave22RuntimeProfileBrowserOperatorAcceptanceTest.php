<?php

declare(strict_types=1);

it('documents cockpit wave 22 runtime profile browser operator acceptance', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/193-wave-22-runtime-profile-browser-operator-acceptance.md');

    expect($report)->toContain('/x/cockpit/diagnostics/runtime-profile')
        ->and($report)->toContain('Operator Activity Runtime Profile')
        ->and($report)->toContain('RUNTIME STATUS')
        ->and($report)->toContain('repository')
        ->and($report)->toContain('journal_handoff')
        ->and($report)->toContain('This diagnostics surface is read-only')
        ->and($report)->toContain('Runtime capabilities remain explicit opt-in')
        ->and($report)->toContain('Must Not Appear')
        ->and($report)->toContain('Enable handoffs')
        ->and($report)->toContain('Save configuration')
        ->and($report)->toContain('php artisan dusk tests/Browser/CockpitRuntimeProfileDiagnosticsSmokeTest.php')
        ->and($report)->toContain('1 passed, 19 assertions')
        ->and($report)->toContain('Human Decision');
});
