<?php

declare(strict_types=1);

use LBHurtado\XChange\Exceptions\VoucherCannotCollect;
use LBHurtado\XChange\Exceptions\VoucherCannotDisburse;
use LBHurtado\XChange\Services\VoucherCapabilityGuard;

it('allows collectible vouchers to collect', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 0.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'target_amount' => 100.00,
            'metadata' => [
                'flow_type' => 'collectible',
            ],
        ],
    ));

    app(VoucherCapabilityGuard::class)->ensureCanCollect($voucher);

    expect(true)->toBeTrue();
});

it('blocks disbursable vouchers from collecting', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 100.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'metadata' => [
                'flow_type' => 'disbursable',
            ],
        ],
    ));

    app(VoucherCapabilityGuard::class)->ensureCanCollect($voucher);
})->throws(VoucherCannotCollect::class);

it('allows disbursable vouchers to disburse', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 100.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'metadata' => [
                'flow_type' => 'disbursable',
            ],
        ],
    ));

    app(VoucherCapabilityGuard::class)->ensureCanDisburse($voucher);

    expect(true)->toBeTrue();
});

it('blocks collectible vouchers from disbursing', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 0.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'target_amount' => 100.00,
            'metadata' => [
                'flow_type' => 'collectible',
            ],
        ],
    ));

    app(VoucherCapabilityGuard::class)->ensureCanDisburse($voucher);
})->throws(VoucherCannotDisburse::class);
