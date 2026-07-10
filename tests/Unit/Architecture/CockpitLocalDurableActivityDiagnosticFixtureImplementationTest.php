<?php

declare(strict_types=1);

use LBHurtado\XChange\Console\Commands\Cockpit\SeedCockpitDiagnosticActivityCommand;

it('documents the cockpit local durable activity diagnostic fixture implementation', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/091-local-durable-activity-diagnostic-fixture-implementation.md';
    $commandPath = $packageRoot.'/src/Console/Commands/Cockpit/SeedCockpitDiagnosticActivityCommand.php';

    expect(file_exists($reportPath))->toBeTrue()
        ->and(file_exists($commandPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $command = file_get_contents($commandPath);
    $provider = file_get_contents($packageRoot.'/src/Providers/XChangeServiceProvider.php');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 4O — Local Durable Activity Diagnostic Fixture Implementation')
        ->and($report)->toContain('php artisan x-change:cockpit:seed-diagnostic-activity --local-only')
        ->and($report)->toContain('refuses to run without `--local-only`')
        ->and($report)->toContain('refuses to run when `app.env` or the application environment is `production`')
        ->and($report)->toContain('DatabaseCockpitOperatorIssuanceActivityRepository')
        ->and($report)->toContain('does not call x-journal')
        ->and($report)->toContain('does not create journal entries')
        ->and($report)->toContain('does not execute actions')
        ->and($report)->toContain('does not send feedback')
        ->and($report)->toContain('does not call providers')
        ->and($report)->toContain('does not mutate vouchers')
        ->and($report)->toContain('does not access wallets')
        ->and($report)->toContain('does not move money')
        ->and($report)->toContain('4 passed, 59 assertions')
        ->and($report)->toContain('Cockpit Mutation Wave 4P — Seeded Diagnostic Fixture Host Verification / Human Visual Handoff')
        ->and($command)->toContain("protected \$signature = 'x-change:cockpit:seed-diagnostic-activity")
        ->and($command)->toContain('local-only')
        ->and($command)->toContain('isProductionEnvironment')
        ->and($command)->toContain('DatabaseCockpitOperatorIssuanceActivityRepository')
        ->and($command)->toContain('fixture-cockpit-journal-diagnostic-activity')
        ->and($command)->toContain('PC-LOCAL-DIAGNOSTIC')
        ->and($provider)->toContain(SeedCockpitDiagnosticActivityCommand::class)
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 4O — Local Durable Activity Diagnostic Fixture Implementation')
        ->and($cockpitCompass)->toContain('reports/091-local-durable-activity-diagnostic-fixture-implementation.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 4O — Local Durable Activity Diagnostic Fixture Implementation')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/091-local-durable-activity-diagnostic-fixture-implementation.md');
});
