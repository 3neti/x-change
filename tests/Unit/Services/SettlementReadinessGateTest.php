<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\SettlementReadinessGateContract;
use LBHurtado\XChange\Exceptions\VoucherRequiresSettlementEnvelope;

it('allows disbursable vouchers through settlement readiness gate', function () {
    $voucher = new Voucher;
    $voucher->code = 'DISB-1234';
    $voucher->setAttribute('metadata', [
        'flow_type' => 'disbursable',
    ]);

    app(SettlementReadinessGateContract::class)->assertReady($voucher);

    expect(true)->toBeTrue();
});

it('allows collectible vouchers through settlement readiness gate', function () {
    $voucher = new Voucher;
    $voucher->code = 'COLL-1234';
    $voucher->setAttribute('metadata', [
        'flow_type' => 'collectible',
    ]);

    app(SettlementReadinessGateContract::class)->assertReady($voucher);

    expect(true)->toBeTrue();
});

it('blocks settlement vouchers when envelope is required', function () {
    $voucher = new Voucher;
    $voucher->code = 'SETTLE-1234';
    $voucher->setAttribute('metadata', [
        'flow_type' => 'settlement',
    ]);

    expect(fn () => app(SettlementReadinessGateContract::class)->assertReady($voucher))
        ->toThrow(VoucherRequiresSettlementEnvelope::class);
});

it('allows settlement vouchers when envelope is not required', function () {
    config()->set('x-change.voucher_flow_types.canonical.settlement.requires_envelope', false);

    $voucher = new Voucher;
    $voucher->code = 'SETTLE-READY';
    $voucher->setAttribute('metadata', [
        'flow_type' => 'settlement',
    ]);

    app(SettlementReadinessGateContract::class)->assertReady($voucher);

    expect(true)->toBeTrue();
});
