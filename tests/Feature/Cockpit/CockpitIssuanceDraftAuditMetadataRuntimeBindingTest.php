<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitIssuanceDraftAuditMetadataBuilderContract;
use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceDraftData;
use LBHurtado\XChange\Services\Cockpit\DefaultCockpitIssuanceDraftAuditMetadataBuilder;

it('binds the cockpit issuance draft audit metadata builder contract', function () {
    $builder = app(CockpitIssuanceDraftAuditMetadataBuilderContract::class);

    expect($builder)->toBeInstanceOf(DefaultCockpitIssuanceDraftAuditMetadataBuilder::class)
        ->and($builder->build(new CockpitIssuanceDraftData(
            template_key: 'money-changer',
            amount: 25,
        ))->status)->toBe('safe');
});
