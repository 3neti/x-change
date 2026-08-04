<?php

declare(strict_types=1);

use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Exceptions\IncompleteClaimEvidence;
use LBHurtado\XChange\Services\Claim\ClaimEvidenceRequirements;

it('snapshots typed issuance requirements as stable scalar values', function (): void {
    $issuance = app(ClaimEvidenceRequirements::class)->snapshot([
        'inputs' => [
            'fields' => [
                VoucherInputField::NAME,
                VoucherInputField::LOCATION,
                VoucherInputField::SELFIE,
            ],
        ],
    ]);

    expect(data_get($issuance, 'metadata.claim_evidence'))->toMatchArray([
        'manifest_version' => 1,
        'requirements' => ['name', 'location', 'selfie'],
        'required_count' => 3,
    ]);
});

it('rejects claim execution when required evidence is incomplete', function (): void {
    $voucher = Voucher::query()->create([
        'code' => 'EVID-GATE',
        'metadata' => [
            'instructions' => [
                'inputs' => ['fields' => ['name', 'location', 'selfie', 'signature']],
                'metadata' => [
                    'claim_evidence' => [
                        'manifest_version' => 1,
                        'requirements' => ['name', 'location', 'selfie', 'signature'],
                    ],
                ],
            ],
        ],
        'state' => 'active',
    ]);

    expect(fn () => app(ClaimEvidenceRequirements::class)->assertComplete($voucher, [
        'inputs' => [
            'name' => 'Amelia Hurtado',
            'location' => [
                'latitude' => 14.5995,
                'longitude' => 121.0288,
            ],
            'selfie' => 'data:image/jpeg;base64,c2VsZmll',
        ],
    ]))->toThrow(IncompleteClaimEvidence::class, 'signature');
});

it('accepts complete identity media location OTP and KYC evidence', function (): void {
    $voucher = Voucher::query()->create([
        'code' => 'EVID-FULL',
        'metadata' => [
            'instructions' => [
                'inputs' => [
                    'fields' => ['name', 'email', 'address', 'birth_date', 'location', 'selfie', 'signature', 'otp', 'kyc'],
                ],
            ],
        ],
        'state' => 'active',
    ]);

    app(ClaimEvidenceRequirements::class)->assertComplete($voucher, [
        'inputs' => [
            'name' => 'Amelia Hurtado',
            'email' => 'amelia@example.test',
            'address' => 'Makati City',
            'birth_date' => '2000-01-01',
            'location' => ['latitude' => 14.5995, 'longitude' => 121.0288],
            'selfie' => 'data:image/jpeg;base64,c2VsZmll',
            'signature' => 'data:image/png;base64,c2lnbmF0dXJl',
            'otp' => ['verified' => true],
            'kyc' => ['status' => 'approved'],
        ],
    ]);

    expect(true)->toBeTrue();
});
