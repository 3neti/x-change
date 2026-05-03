<?php

declare(strict_types=1);

use LBHurtado\XChange\Exceptions\VoucherRequiresSettlementEnvelope;
use LBHurtado\XChange\Services\SettlementCollectionGate;

beforeEach(function () {
    config()->set('x-change.settlement.default_driver', 'philhealth-bst');
    config()->set('x-change.settlement.drivers_path', settlementEnvelopeDriversPath());
});

it('blocks settlement collection when envelope is not ready', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'metadata' => [
                'flow_type' => 'settlement',
                'settlement_driver' => 'philhealth-bst',
            ],
        ],
    ));

    $voucher = persistUnreadySettlementEnvelopeEvidence($voucher);

    app(SettlementCollectionGate::class)->ensureCollectibleSettlementIsReady(
        voucher: $voucher,
        context: app(SettlementCollectionGate::class)->contextFromVoucher($voucher),
    );
})->throws(VoucherRequiresSettlementEnvelope::class);

it('allows settlement collection when envelope is ready', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'metadata' => [
                'flow_type' => 'settlement',
                'settlement_driver' => 'philhealth-bst',
            ],
        ],
    ));

    $voucher = persistReadySettlementEnvelopeEvidence($voucher);

    app(SettlementCollectionGate::class)->ensureCollectibleSettlementIsReady(
        voucher: $voucher,
        context: app(SettlementCollectionGate::class)->contextFromVoucher($voucher),
    );

    expect(true)->toBeTrue();
});

it('blocks settlement voucher disbursement to claimant', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'metadata' => [
                'flow_type' => 'settlement',
                'settlement_driver' => 'philhealth-bst',
            ],
        ],
    ));

    app(SettlementCollectionGate::class)->assertSettlementDoesNotDisburse($voucher);
})->throws(VoucherRequiresSettlementEnvelope::class);

it('does not gate ordinary collectible vouchers with settlement readiness', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'metadata' => [
                'flow_type' => 'collectible',
            ],
        ],
    ));

    app(SettlementCollectionGate::class)->ensureCollectibleSettlementIsReady(
        voucher: $voucher,
        context: [],
    );

    expect(true)->toBeTrue();
});

it('does not block ordinary disbursable vouchers as settlement disbursements', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'metadata' => [
                'flow_type' => 'disbursable',
            ],
        ],
    ));

    app(SettlementCollectionGate::class)->assertSettlementDoesNotDisburse($voucher);

    expect(true)->toBeTrue();
});

function persistReadySettlementEnvelopeEvidence($voucher): object
{
    $metadata = is_array($voucher->metadata ?? null)
        ? $voucher->metadata
        : [];

    $voucher->forceFill([
        'metadata' => [
            ...$metadata,
            'flow_type' => 'settlement',
            'settlement_driver' => 'philhealth-bst',
            'settlement_payload' => [
                'patient_name' => 'Juan Dela Cruz',
                'patient_mobile' => '09171234567',
            ],
            'settlement_checklist' => [
                'amount_verified' => true,
            ],
        ],
    ])->save();

    return $voucher->refresh();
}

function persistUnreadySettlementEnvelopeEvidence($voucher): object
{
    $metadata = is_array($voucher->metadata ?? null)
        ? $voucher->metadata
        : [];

    $voucher->forceFill([
        'metadata' => [
            ...$metadata,
            'flow_type' => 'settlement',
            'settlement_driver' => 'philhealth-bst',
            'settlement_payload' => [
                'patient_name' => 'Juan Dela Cruz',
                'patient_mobile' => '09171234567',
            ],
            'settlement_checklist' => [
                'amount_verified' => false,
            ],
        ],
    ])->save();

    return $voucher->refresh();
}
