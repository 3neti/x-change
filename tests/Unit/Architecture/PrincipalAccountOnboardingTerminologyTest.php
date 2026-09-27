<?php

declare(strict_types=1);

it('locks principal and account onboarding terminology across entry documentation', function () {
    $packageRoot = dirname(__DIR__, 3);
    $readme = file_get_contents($packageRoot.'/README.md');
    $onboarding = file_get_contents($packageRoot.'/ONBOARDING.md');
    $gettingStarted = file_get_contents($packageRoot.'/GETTING_STARTED.md');
    $doctrine = file_get_contents($packageRoot.'/docs/governance/COMMISSIONING_MAKER_CHECKER_DOCTRINE.md');
    $terminology = file_get_contents($packageRoot.'/docs/terminology-lock.md');
    $readmeIntroduction = strstr($readme, '## The model in one minute', true);

    expect($readmeIntroduction)
        ->toContain('[Principals, Accounts, and Human Onboarding](./ONBOARDING.md)')
        ->toContain('System Principal')
        ->toContain('System Operations Account')
        ->toContain('Commercial Principal')
        ->toContain('Commercial Revenue Account')
        ->and($onboarding)
        ->toContain('System Operations Account')
        ->toContain('Commercial Revenue Account')
        ->toContain('Neither non-human principal requires a personal email address or mobile')
        ->toContain('The System Principal cannot be a Maker or Checker.')
        ->toContain('Customer charging must remain fail-closed')
        ->toContain('non-personal technical')
        ->and($gettingStarted)
        ->toContain('System Principal and System Operations Account')
        ->toContain('[Principals, Accounts, and Human Onboarding](./ONBOARDING.md)')
        ->and($doctrine)
        ->toContain('Commercial Principal and Commercial Revenue Account binding')
        ->and($terminology)
        ->toContain('Do not use **Service Principal** for the legal seller');
});
