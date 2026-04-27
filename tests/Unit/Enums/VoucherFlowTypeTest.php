<?php

use LBHurtado\XChange\Enums\VoucherFlowType;

it('normalizes canonical flow types', function () {
    expect(VoucherFlowType::normalize('disbursable'))->toBe(VoucherFlowType::Disbursable);
    expect(VoucherFlowType::normalize('collectible'))->toBe(VoucherFlowType::Collectible);
    expect(VoucherFlowType::normalize('settlement'))->toBe(VoucherFlowType::Settlement);
});

it('normalizes legacy flow aliases', function () {
    config()->set('x-change.voucher_flow_types.aliases', [
        'redeemable' => 'disbursable',
        'payable' => 'collectible',
    ]);

    expect(VoucherFlowType::normalize('redeemable'))->toBe(VoucherFlowType::Disbursable);
    expect(VoucherFlowType::normalize('payable'))->toBe(VoucherFlowType::Collectible);
});

it('falls back to disbursable by default', function () {
    expect(VoucherFlowType::normalize(null))->toBe(VoucherFlowType::Disbursable);
});

it('exposes labels and directions', function () {
    expect(VoucherFlowType::Disbursable->label())->toBe('Cash Out Voucher');
    expect(VoucherFlowType::Disbursable->direction())->toBe('outward');

    expect(VoucherFlowType::Collectible->label())->toBe('Pay In Voucher');
    expect(VoucherFlowType::Collectible->direction())->toBe('inward');

    expect(VoucherFlowType::Settlement->label())->toBe('Settlement Voucher');
    expect(VoucherFlowType::Settlement->direction())->toBe('bilateral');
});
