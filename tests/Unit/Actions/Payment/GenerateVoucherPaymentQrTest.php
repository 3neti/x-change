<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Payment\GenerateVoucherPaymentQr;
use LBHurtado\XChange\Contracts\VoucherPaymentQrGeneratorContract;
use LBHurtado\XChange\Data\Payment\VoucherPaymentQrData;
use LBHurtado\XChange\Exceptions\VoucherCannotCollect;

it('generates payment qr metadata for collectible vouchers', function () {
    $voucher = new Voucher;
    $voucher->code = 'PAY-1234';
    $voucher->setAttribute('metadata', [
        'flow_type' => 'collectible',
    ]);

    $result = app(GenerateVoucherPaymentQr::class)->handle($voucher);

    expect($result)->toBeInstanceOf(VoucherPaymentQrData::class);
    expect($result->voucher_code)->toBe('PAY-1234');
    expect($result->flow_type)->toBe('collectible');
    expect($result->route_key)->toBe('pay');
    expect($result->url)->toContain('/pay/PAY-1234');
    expect($result->qr_type)->toBe('payment');
    expect($result->capabilities['can_collect'])->toBeTrue();
});

it('rejects disbursable vouchers from payment qr generation', function () {
    $voucher = new Voucher;
    $voucher->code = 'DISB-1234';
    $voucher->setAttribute('metadata', [
        'flow_type' => 'disbursable',
    ]);

    expect(fn () => app(GenerateVoucherPaymentQr::class)->handle($voucher))
        ->toThrow(VoucherCannotCollect::class);
});

it('allows settlement vouchers to generate payment qr metadata when collect capable', function () {
    $voucher = new Voucher;
    $voucher->code = 'SETTLE-PAY';
    $voucher->setAttribute('metadata', [
        'flow_type' => 'settlement',
    ]);

    $result = app(GenerateVoucherPaymentQr::class)->handle($voucher);

    expect($result)->toBeInstanceOf(VoucherPaymentQrData::class);
    expect($result->voucher_code)->toBe('SETTLE-PAY');
    expect($result->flow_type)->toBe('settlement');
    expect($result->route_key)->toBe('settle');
    expect($result->qr_type)->toBe('hybrid');
    expect($result->capabilities['can_collect'])->toBeTrue();
    expect($result->capabilities['can_settle'])->toBeTrue();
});

it('maps legacy payable vouchers to collectible payment qr metadata', function () {
    $voucher = new Voucher;
    $voucher->code = 'LEGACY-PAY';
    $voucher->setAttribute('metadata', [
        'voucher_type' => 'payable',
    ]);

    $result = app(GenerateVoucherPaymentQr::class)->handle($voucher);

    expect($result->flow_type)->toBe('collectible');
    expect($result->route_key)->toBe('pay');
});

it('delegates generation through the payment qr generator contract', function () {
    $voucher = new Voucher;
    $voucher->code = 'MOCK-PAY';

    $expected = new VoucherPaymentQrData(
        voucher_code: 'MOCK-PAY',
        flow_type: 'collectible',
        route_key: 'pay',
        url: 'http://localhost/pay/MOCK-PAY',
        qr_type: 'payment',
        capabilities: [
            'can_collect' => true,
        ],
    );

    $generator = Mockery::mock(VoucherPaymentQrGeneratorContract::class);
    $generator->shouldReceive('generate')
        ->once()
        ->with($voucher)
        ->andReturn($expected);

    $action = new GenerateVoucherPaymentQr($generator);

    expect($action->handle($voucher))->toBe($expected);
});
