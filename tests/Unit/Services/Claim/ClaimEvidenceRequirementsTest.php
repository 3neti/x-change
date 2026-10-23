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

    expect(data_get($issuance, 'metadata.custom.claim_evidence'))->toMatchArray([
        'manifest_version' => 1,
        'requirements' => ['name', 'location', 'selfie'],
        'required_count' => 3,
    ]);
});

it('includes blocking validation evidence in the frozen issuance manifest', function (): void {
    $issuance = app(ClaimEvidenceRequirements::class)->snapshot([
        'inputs' => ['fields' => ['name']],
        'validation' => [
            'otp' => ['required' => true, 'on_failure' => 'block'],
            'face_match' => ['required' => true, 'on_failure' => 'warn'],
        ],
    ]);

    expect(data_get($issuance, 'metadata.custom.claim_evidence.requirements'))->toBe(['name', 'otp'])
        ->and(data_get($issuance, 'metadata.custom.claim_evidence.required_count'))->toBe(2);
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
    $image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
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
            'selfie' => $image,
            'signature' => $image,
            'otp' => [
                'verified' => true,
                'verification_reference' => 'otp-123',
                'verification_purpose' => 'claim',
                'verified_at' => now()->toIso8601String(),
            ],
            'kyc' => ['status' => 'approved', 'transaction_id' => 'kyc-123'],
        ],
    ]);

    expect(true)->toBeTrue();
});

it('rejects forged verification flags and invalid image or coordinates', function (): void {
    $voucher = Voucher::query()->create([
        'code' => 'EVID-FORGED',
        'metadata' => ['instructions' => [
            'inputs' => ['fields' => ['otp', 'kyc', 'selfie', 'location']],
        ]],
        'state' => 'active',
    ]);

    expect(fn () => app(ClaimEvidenceRequirements::class)->assertComplete($voucher, [
        'inputs' => [
            'otp' => ['verified' => true],
            'kyc' => ['status' => 'approved'],
            'selfie' => 'data:image/png;base64,'.base64_encode('not an image'),
            'location' => ['latitude' => 123, 'longitude' => 400],
        ],
    ]))->toThrow(IncompleteClaimEvidence::class);
});

it('requires structural OTP and face-match proof for blocking validation rules', function (): void {
    $voucher = Voucher::query()->create([
        'code' => 'EVID-VALIDATION-RULES',
        'metadata' => ['instructions' => validVoucherInstructions(overrides: [
            'validation' => [
                'otp' => ['required' => true, 'on_failure' => 'block'],
                'face_match' => ['required' => true, 'on_failure' => 'block'],
            ],
        ])->toArray()],
        'state' => 'active',
    ]);

    expect(app(ClaimEvidenceRequirements::class)->forVoucher($voucher))->toBe(['otp', 'kyc']);
    expect(fn () => app(ClaimEvidenceRequirements::class)->assertComplete($voucher, [
        'inputs' => [
            'otp' => ['verified' => true],
            'kyc' => [
                'status' => 'approved',
                'face_verification' => ['verified' => true, 'face_match' => true],
            ],
        ],
    ]))->toThrow(IncompleteClaimEvidence::class);
});
