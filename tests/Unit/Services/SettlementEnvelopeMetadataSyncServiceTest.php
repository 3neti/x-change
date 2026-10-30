<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\SettlementEnvelopeMetadataSyncService;

beforeEach(function () {
    config()->set('x-change.settlement.default_driver', 'philhealth-bst');
});

it('uses the v2 instruction driver when recording an attestation', function () {
    $voucher = issueVoucher(validVoucherInstructions());
    $metadata = $voucher->metadata;
    data_set($metadata, 'instructions.execution', [
        'schema' => 'voucher.execution.v2',
        'settlement' => ['driver' => 'aui-application'],
    ]);
    $voucher->forceFill(['metadata' => $metadata])->save();

    $updated = app(SettlementEnvelopeMetadataSyncService::class)
        ->syncPatientAttestation($voucher->refresh(), ['inputs' => ['name' => 'Test Patient']]);

    expect($updated->metadata['settlement_envelope']['driver'])->toBe('aui-application');
});

it('rejects conflicting persisted envelope and v2 instruction drivers', function () {
    $voucher = issueVoucher(validVoucherInstructions());
    $metadata = $voucher->metadata;
    data_set($metadata, 'instructions.execution', [
        'schema' => 'voucher.execution.v2',
        'settlement' => ['driver' => 'aui-application'],
    ]);
    data_set($metadata, 'settlement_envelope.driver', 'philhealth-bst');
    $voucher->forceFill(['metadata' => $metadata])->save();

    expect(fn () => app(SettlementEnvelopeMetadataSyncService::class)
        ->syncPatientAttestation($voucher->refresh(), []))
        ->toThrow(InvalidArgumentException::class, 'does not match');
});

it('persists patient attestation into settlement envelope metadata', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'metadata' => [
                'flow_type' => 'settlement',
                'settlement_driver' => 'philhealth-bst',
            ],
        ],
    ));

    $voucher = app(SettlementEnvelopeMetadataSyncService::class)
        ->syncPatientAttestation($voucher, [
            'claim_type' => 'redeem',
            'mobile' => '639171234567',
            'inputs' => [
                'name' => 'Juan Dela Cruz',
                'signature' => 'demo-signature',
            ],
            'bio_fields' => [
                'name' => 'Juan Dela Cruz',
            ],
            'wallet_info' => [
                'mobile' => '09171234567',
            ],
        ]);

    expect($voucher->metadata['settlement_envelope']['driver'])->toBe('philhealth-bst')
        ->and($voucher->metadata['settlement_envelope']['payload'])->toMatchArray([
            'patient_name' => 'Juan Dela Cruz',
            'patient_mobile' => '09171234567',
        ])
        ->and($voucher->metadata['settlement_envelope']['attestation'])->toMatchArray([
            'attested' => true,
            'claim_type' => 'redeem',
            'mobile' => '639171234567',
        ])
        ->and($voucher->metadata['settlement_payload'])->toMatchArray([
            'patient_name' => 'Juan Dela Cruz',
            'patient_mobile' => '09171234567',
        ]);
});

it('preserves existing settlement envelope documents and checklist', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'metadata' => [
                'flow_type' => 'settlement',
                'settlement_driver' => 'philhealth-bst',
            ],
        ],
    ));

    $voucher->forceFill([
        'metadata' => [
            'flow_type' => 'settlement',
            'settlement_driver' => 'philhealth-bst',
            'settlement_envelope' => [
                'driver' => 'philhealth-bst',
                'payload' => [
                    'diagnosis' => 'Demo diagnosis',
                ],
                'documents' => [
                    'loa' => 'demo://documents/loa.pdf',
                ],
                'checklist' => [
                    'amount_verified' => true,
                ],
            ],
        ],
    ])->save();

    $voucher = app(SettlementEnvelopeMetadataSyncService::class)
        ->syncPatientAttestation($voucher->refresh(), [
            'inputs' => [
                'name' => 'Juan Dela Cruz',
            ],
            'wallet_info' => [
                'mobile' => '09171234567',
            ],
        ]);

    expect($voucher->metadata['settlement_envelope']['payload'])->toMatchArray([
        'diagnosis' => 'Demo diagnosis',
        'patient_name' => 'Juan Dela Cruz',
        'patient_mobile' => '09171234567',
    ])
        ->and($voucher->metadata['settlement_envelope']['documents'])->toMatchArray([
            'loa' => 'demo://documents/loa.pdf',
        ])
        ->and($voucher->metadata['settlement_envelope']['checklist'])->toMatchArray([
            'amount_verified' => true,
        ]);
});

it('lets explicit settlement payload override derived patient payload', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'metadata' => [
                'flow_type' => 'settlement',
                'settlement_driver' => 'philhealth-bst',
            ],
        ],
    ));

    $voucher = app(SettlementEnvelopeMetadataSyncService::class)
        ->syncPatientAttestation($voucher, [
            'inputs' => [
                'name' => 'Derived Patient',
            ],
            'wallet_info' => [
                'mobile' => '09171234567',
            ],
            'settlement' => [
                'payload' => [
                    'patient_name' => 'Explicit Patient',
                ],
            ],
        ]);

    expect($voucher->metadata['settlement_envelope']['payload'])->toMatchArray([
        'patient_name' => 'Explicit Patient',
        'patient_mobile' => '09171234567',
    ]);
});
