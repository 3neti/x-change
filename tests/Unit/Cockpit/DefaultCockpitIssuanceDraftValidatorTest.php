<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceDraftData;
use LBHurtado\XChange\Services\Cockpit\DefaultCockpitIssuanceDraftValidator;
use LBHurtado\XChange\Services\Cockpit\DefaultCockpitIssuanceTemplateRegistry;

function cockpitDraftValidator(): DefaultCockpitIssuanceDraftValidator
{
    return new DefaultCockpitIssuanceDraftValidator(new DefaultCockpitIssuanceTemplateRegistry);
}

it('accepts a known enabled issuance draft template', function () {
    $result = cockpitDraftValidator()->validate(new CockpitIssuanceDraftData(
        template_key: 'money-changer',
        amount: 25,
        currency: 'PHP',
    ));

    expect($result->valid)->toBeTrue()
        ->and($result->errors)->toBe([])
        ->and($result->metadata['template_resolved'])->toBeTrue();
});

it('accepts the settlement envelope issuance draft template exposed by quick generate', function () {
    $result = cockpitDraftValidator()->validate(new CockpitIssuanceDraftData(
        template_key: 'settlement-envelope',
        amount: 1000,
        currency: 'PHP',
    ));

    expect($result->valid)->toBeTrue()
        ->and($result->errors)->toBe([])
        ->and($result->metadata['template_enabled'])->toBeTrue();
});

it('rejects unknown templates before issuance', function (string $template, string $error) {
    $result = cockpitDraftValidator()->validate(new CockpitIssuanceDraftData(
        template_key: $template,
        amount: 25,
    ));

    expect($result->valid)->toBeFalse()
        ->and($result->errors)->toContain($error);
})->with([
    ['imaginary-template', 'template_unknown'],
]);

it('rejects missing amount before compiling for issuance', function () {
    $result = cockpitDraftValidator()->validate(new CockpitIssuanceDraftData(
        template_key: 'money-changer',
        amount: 0,
    ));

    expect($result->valid)->toBeFalse()
        ->and($result->errors)->toContain('amount_required');
});
