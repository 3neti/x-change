<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\PayCodePresentationResolverContract;

it('resolves disbursable pay code presentation', function () {
    $voucher = new Voucher;
    $voucher->code = 'DISB-1234';
    $voucher->setAttribute('metadata', [
        'flow_type' => 'disbursable',
    ]);

    $result = app(PayCodePresentationResolverContract::class)->resolve($voucher);

    expect($result['voucher_code'])->toBe('DISB-1234');
    expect($result['flow_type'])->toBe('disbursable');
    expect($result['route_key'])->toBe('disburse');
    expect($result['url'])->toContain('/disburse/DISB-1234');
    expect($result['qr_type'])->toBe('claim');
    expect($result['capabilities']['can_disburse'])->toBeTrue();
});

it('resolves collectible pay code presentation', function () {
    $voucher = new Voucher;
    $voucher->code = 'COLL-1234';
    $voucher->setAttribute('metadata', [
        'flow_type' => 'collectible',
    ]);

    $result = app(PayCodePresentationResolverContract::class)->resolve($voucher);

    expect($result['voucher_code'])->toBe('COLL-1234');
    expect($result['flow_type'])->toBe('collectible');
    expect($result['route_key'])->toBe('pay');
    expect($result['url'])->toContain('/pay/COLL-1234');
    expect($result['qr_type'])->toBe('payment');
    expect($result['capabilities']['can_collect'])->toBeTrue();
});

it('resolves settlement pay code presentation', function () {
    $voucher = new Voucher;
    $voucher->code = 'SETTLE-1234';
    $voucher->setAttribute('metadata', [
        'flow_type' => 'settlement',
    ]);

    $result = app(PayCodePresentationResolverContract::class)->resolve($voucher);

    expect($result['voucher_code'])->toBe('SETTLE-1234');
    expect($result['flow_type'])->toBe('settlement');
    expect($result['route_key'])->toBe('settle');
    expect($result['url'])->toContain('/settle/SETTLE-1234');
    expect($result['qr_type'])->toBe('hybrid');
    expect($result['capabilities']['can_settle'])->toBeTrue();
});

it('resolves legacy redeemable alias as disbursable presentation', function () {
    $voucher = new Voucher;
    $voucher->code = 'LEGACY-REDEEM';
    $voucher->setAttribute('metadata', [
        'voucher_type' => 'redeemable',
    ]);

    $result = app(PayCodePresentationResolverContract::class)->resolve($voucher);

    expect($result['flow_type'])->toBe('disbursable');
    expect($result['route_key'])->toBe('disburse');
});

it('resolves legacy payable alias as collectible presentation', function () {
    $voucher = new Voucher;
    $voucher->code = 'LEGACY-PAY';
    $voucher->setAttribute('metadata', [
        'voucher_type' => 'payable',
    ]);

    $result = app(PayCodePresentationResolverContract::class)->resolve($voucher);

    expect($result['flow_type'])->toBe('collectible');
    expect($result['route_key'])->toBe('pay');
});
