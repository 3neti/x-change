<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\SettlementReadinessGateContract;
use LBHurtado\XChange\Exceptions\VoucherRequiresSettlementEnvelope;

it('blocks settlement voucher when philhealth bst envelope is not settleable', function () {
    config()->set('x-change.settlement.default_driver', 'philhealth-bst');
    config()->set('x-change.settlement.drivers_path', settlementEnvelopeDriversPath());

    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'metadata' => [
                'flow_type' => 'settlement',
                'settlement_driver' => 'philhealth-bst',
            ],
        ],
    ));

    app(SettlementReadinessGateContract::class)->ensureReady(
        voucher: $voucher,
        gate: 'settleable',
        context: [
            'payload' => [
                'patient_name' => 'Juan Dela Cruz',
                'patient_mobile' => '09171234567',
            ],
            'checklist' => [
                'amount_verified' => false,
            ],
        ],
    );
})->throws(VoucherRequiresSettlementEnvelope::class);

it('allows settlement voucher when philhealth bst envelope is settleable', function () {
    config()->set('x-change.settlement.default_driver', 'philhealth-bst');
    config()->set('x-change.settlement.drivers_path', settlementEnvelopeDriversPath());

    $voucher = issueVoucher(validVoucherInstructions(
        overrides: [
            'metadata' => [
                'flow_type' => 'settlement',
                'settlement_driver' => 'philhealth-bst',
            ],
        ],
    ));

    app(SettlementReadinessGateContract::class)->ensureReady(
        voucher: $voucher,
        gate: 'settleable',
        context: [
            'payload' => [
                'patient_name' => 'Juan Dela Cruz',
                'patient_mobile' => '09171234567',
            ],
            'checklist' => [
                'amount_verified' => true,
            ],
        ],
    );

    expect(true)->toBeTrue();
});
