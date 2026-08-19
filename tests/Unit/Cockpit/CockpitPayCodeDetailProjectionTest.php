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

it('projects only the sanitized durable slice ledger', function (): void {
    $projection = (new CockpitPayCodeDetailProjection)->slices([
        'mode' => 'scheduled',
        'mode_label' => 'Scheduled',
        'selection' => 'one_or_many',
        'currency' => 'PHP',
        'total_minor' => 10_000,
        'consumed_minor' => 5_000,
        'reserved_minor' => 0,
        'available_minor' => 5_000,
        'rows' => [
            [
                'id' => 'fare_one',
                'label' => 'Morning fare',
                'amount_minor' => 5_000,
                'status' => 'consumed',
                'provider_payload' => ['secret' => 'hidden'],
            ],
        ],
        'provider_payload' => ['secret' => 'hidden'],
    ]);

    expect($projection)->toMatchArray([
        'schema' => 'x-change.cockpit.pay-code-slices.v1',
        'mode' => 'scheduled',
        'available_minor' => 5_000,
        'raw_payload_exposed' => false,
    ])->and($projection)->not->toHaveKey('provider_payload')
        ->and(data_get($projection, 'rows.0.provider_payload'))->toBeNull();
});
