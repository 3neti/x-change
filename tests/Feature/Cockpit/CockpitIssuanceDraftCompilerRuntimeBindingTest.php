<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CockpitIssuanceDraftCompilerContract;
use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceDraftData;
use LBHurtado\XChange\Services\Cockpit\DefaultCockpitIssuanceDraftCompiler;

it('binds the cockpit issuance draft compiler contract to the default compiler', function () {
    $compiler = app(CockpitIssuanceDraftCompilerContract::class);

    expect($compiler)->toBeInstanceOf(DefaultCockpitIssuanceDraftCompiler::class);
});

it('resolves compiler through the runtime seam without invoking issuance', function () {
    $payload = app(CockpitIssuanceDraftCompilerContract::class)->compile(new CockpitIssuanceDraftData(
        template_key: 'money-changer',
        amount: 25,
        recipient_reference: '09173011987',
    ));

    expect(data_get($payload, 'cash.amount'))->toBe(25)
        ->and(data_get($payload, 'metadata.custom.cockpit.template_key'))->toBe('money-changer');
});
