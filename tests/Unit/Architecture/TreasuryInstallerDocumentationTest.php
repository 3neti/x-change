<?php

declare(strict_types=1);

it('documents the fail-closed first install and idempotent reinstall lifecycle', function () {
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
        'Installer lifecycle classification',
        '**fresh or resumable**',
        '**initialized**',
        '**incomplete or conflicting**',
        'Treasury already initialized [netbank-primary]; skipping opening live preflight and reconciliation.',
        'provider-balance-below-internal-attribution',
        'No authoritative missing disbursement postings explain the Treasury deficit.',
        'no Treasury Position is provisioned for that',
        'optional connection',
        'dns_resolution_failed',
        'invalid_balance_response',
        'sensitive provider URLs',
    )->and($manual)->toContain(
        'static Treasury configuration and canonical topology classification',
        'live provider preflight',
        'UI and remaining asset publication',
        'First Install Versus Reinstall',
        'php artisan x-change:install --force && php artisan optimize:clear && npm run dev',
        'does not call the provider',
        'Operational Treasury Drift Is Not Installation',
        'status=rejected',
        'Explicitly defer Treasury initialization',
        '`--no-interaction` never',
    );
});
