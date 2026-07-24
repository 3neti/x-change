<?php

declare(strict_types=1);

it('documents the public claim payment and topup boundaries', function () {
    $documentation = file_get_contents(
        dirname(__DIR__, 3).'/docs/architecture/PUBLIC_PAY_CODE_EXPERIENCE_BOUNDARIES.md',
    );

    expect($documentation)
        ->toContain('/x/claim/{code}')
        ->toContain('/x/pay/{code}')
        ->toContain('/x/cockpit/funding')
        ->toContain('machine-readable claim-experience')
        ->toContain('Account Funding Receipt')
        ->toContain('fresh pre-transaction validation token')
        ->toContain('Retrying the same payment attempt');
});
