<?php

declare(strict_types=1);

it('documents the fail-closed live Treasury installer lifecycle', function () {
    $packageRoot = dirname(__DIR__, 3);
    $architecture = file_get_contents(
        $packageRoot.'/docs/architecture/TREASURY_INITIAL_STATE_AND_ACCOUNT_PORTFOLIOS.md',
    );
    $manual = file_get_contents(
        $packageRoot.'/docs/customization/x-change-install-onboarding-manual.md',
    );

    expect($architecture)->toContain(
        'php artisan x-change:treasury:preflight --live --no-interaction',
        '`--force` controls replacement of published files only.',
        'A failed required connection makes live preflight and installation fail closed.',
        'no Treasury Position is provisioned for that optional',
        'dns_resolution_failed',
        'invalid_balance_response',
        'sensitive provider URLs',
    )->and($manual)->toContain(
        'static Treasury configuration preflight',
        'live provider preflight',
        'UI and remaining asset publication',
        'Explicitly defer Treasury initialization',
        '`--no-interaction` never',
    );
});
