<?php

declare(strict_types=1);

use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\XChange\Services\Cockpit\CockpitPayCodeDetailProjection;

it('presents claim requirements from supported instruction input shapes', function (array $inputs): void {
    $projection = new CockpitPayCodeDetailProjection;

    $instructions = $projection->instructions([
        'instructions' => [
            'inputs' => $inputs,
        ],
    ]);

    $claimRequirements = collect($instructions['groups'])->firstWhere('key', 'claim');

    expect($claimRequirements)->not->toBeNull()
        ->and($claimRequirements['facts'])->toContainEqual([
            'label' => 'Required Inputs',
            'value' => 'Mobile, Signature, Name, Birth Date, Location, Selfie',
        ]);
})->with([
    'serialized DTO list' => [[
        'mobile',
        'signature',
        'name',
        'birth_date',
        'location',
        'selfie',
    ]],
    'canonical nested fields' => [[
        'fields' => [
            'mobile',
            'signature',
            'name',
            'birth_date',
            'location',
            'selfie',
        ],
    ]],
    'typed voucher input fields' => [[
        'fields' => [
            VoucherInputField::MOBILE,
            VoucherInputField::SIGNATURE,
            VoucherInputField::NAME,
            VoucherInputField::BIRTH_DATE,
            VoucherInputField::LOCATION,
            VoucherInputField::SELFIE,
        ],
    ]],
]);
