<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitIssuanceDraftValidatorContract;
use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceDraftData;
use LBHurtado\XChange\Services\Cockpit\DefaultCockpitIssuanceDraftValidator;

it('binds the cockpit issuance draft validator contract', function () {
    $validator = app(CockpitIssuanceDraftValidatorContract::class);

    expect($validator)->toBeInstanceOf(DefaultCockpitIssuanceDraftValidator::class)
        ->and($validator->validate(new CockpitIssuanceDraftData(
            template_key: 'money-changer',
            amount: 25,
        ))->valid)->toBeTrue();
});
