<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitIssuanceTemplateRegistryContract;
use LBHurtado\XChange\Services\Cockpit\DefaultCockpitIssuanceTemplateRegistry;

it('binds the cockpit issuance template registry contract', function () {
    $registry = app(CockpitIssuanceTemplateRegistryContract::class);

    expect($registry)->toBeInstanceOf(DefaultCockpitIssuanceTemplateRegistry::class)
        ->and($registry->resolve('money-changer')?->enabled)->toBeTrue()
        ->and($registry->resolve('settlement-envelope')?->enabled)->toBeFalse();
});
