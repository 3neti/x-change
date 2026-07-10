<?php

declare(strict_types=1);

it('documents synthetic fixture cleanup decision and brickmath cleanup planning', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/099-synthetic-fixture-local-cleanup-decision-brickmath-cleanup-planning.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 5E — Synthetic Fixture Local Cleanup Decision / BrickMath Cleanup Planning')
        ->and($report)->toContain('Status: Decision recorded')
        ->and($report)->toContain('Do not remove the `PC-LOCAL-DIAGNOSTIC` synthetic fixture row in this checkpoint.')
        ->and($report)->toContain('Keep `PC-LOCAL-DIAGNOSTIC` for now.')
        ->and($report)->toContain('Keep `MCPC` in the local database.')
        ->and($report)->toContain('Keep the local database repository and recorder enabled for manual testing.')
        ->and($report)->toContain('Do not production-enable durable activity recording by default.')
        ->and($report)->toContain('Passing floats to BigNumber::of()')
        ->and($report)->toContain('GeneratePayCode::requiredIssuanceAmount()')
        ->and($report)->toContain('EstimatePayCodeCost::handle()')
        ->and($report)->toContain('InstructionRevenueAllocatorService::allocate()')
        ->and($report)->toContain('VoucherIssuancePayloadNormalizer')
        ->and($report)->toContain('Cockpit / issuance cleanup — Monetary String Normalization for BrickMath Compatibility')
        ->and($report)->toContain('This checkpoint does not execute that deletion.')
        ->and($report)->toContain('database writes;')
        ->and($report)->toContain('database deletes;')
        ->and($report)->toContain('Cockpit Mutation Wave 5F — BrickMath Monetary Normalization Characterization')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 5E — Synthetic Fixture Local Cleanup Decision / BrickMath Cleanup Planning')
        ->and($cockpitCompass)->toContain('reports/099-synthetic-fixture-local-cleanup-decision-brickmath-cleanup-planning.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 5E — Synthetic Fixture Local Cleanup Decision / BrickMath Cleanup Planning')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/099-synthetic-fixture-local-cleanup-decision-brickmath-cleanup-planning.md');
});
