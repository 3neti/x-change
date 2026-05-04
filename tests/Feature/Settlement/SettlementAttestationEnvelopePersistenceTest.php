<?php

declare(strict_types=1);

use LBHurtado\XChange\Exceptions\VoucherRequiresSettlementEnvelope;
use LBHurtado\XChange\Services\SettlementCollectionGate;
use LBHurtado\XChange\Services\SettlementEnvelopeMetadataSyncService;

beforeEach(function () {
    config()->set('x-change.settlement.default_driver', 'philhealth-bst');
    config()->set('x-change.settlement.drivers_path', settlementEnvelopeDriversPath());
});

it('makes patient attestation payload available to settlement collection gate context', function () {
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
                'name' => 'Juan Dela Cruz',
            ],
            'wallet_info' => [
                'mobile' => '09171234567',
            ],
        ]);

    $context = app(SettlementCollectionGate::class)
        ->contextFromVoucher($voucher);

    expect($context['driver'])->toBe('philhealth-bst')
        ->and($context['payload'])->toMatchArray([
            'patient_name' => 'Juan Dela Cruz',
            'patient_mobile' => '09171234567',
        ]);
});

it('keeps settlement collection blocked after attestation until amount is verified', function () {
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
                'name' => 'Juan Dela Cruz',
            ],
            'wallet_info' => [
                'mobile' => '09171234567',
            ],
        ]);

    app(SettlementCollectionGate::class)->ensureCollectibleSettlementIsReady(
        voucher: $voucher,
        context: app(SettlementCollectionGate::class)->contextFromVoucher($voucher),
    );
})->throws(VoucherRequiresSettlementEnvelope::class);
