<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\Payment\RenderVoucherPaymentQr;
use LBHurtado\XChange\Contracts\VoucherPaymentQrRendererContract;
use LBHurtado\XChange\Data\Payment\RenderedVoucherPaymentQrData;
use LBHurtado\XChange\Data\Payment\VoucherPaymentQrData;

it('renders payment qr metadata as provider neutral json payload', function () {
    $qr = new VoucherPaymentQrData(
        voucher_code: 'PAY-1234',
        flow_type: 'collectible',
        route_key: 'pay',
        url: 'http://localhost/pay/PAY-1234',
        qr_type: 'payment',
        capabilities: [
            'can_collect' => true,
        ],
    );

    $result = app(RenderVoucherPaymentQr::class)->handle($qr);

    expect($result)->toBeInstanceOf(RenderedVoucherPaymentQrData::class);
    expect($result->voucher_code)->toBe('PAY-1234');
    expect($result->format)->toBe('json');
    expect($result->content_type)->toBe('application/json');
    expect($result->payload)->toMatchArray([
        'type' => 'x-change.payment_qr',
        'voucher_code' => 'PAY-1234',
        'flow_type' => 'collectible',
        'route_key' => 'pay',
        'url' => 'http://localhost/pay/PAY-1234',
        'qr_type' => 'payment',
    ]);

    expect($result->rendered)->toBeJson();
});

it('delegates rendering through the payment qr renderer contract', function () {
    $qr = new VoucherPaymentQrData(
        voucher_code: 'PAY-1234',
        flow_type: 'collectible',
        route_key: 'pay',
        url: 'http://localhost/pay/PAY-1234',
        qr_type: 'payment',
    );

    $expected = new RenderedVoucherPaymentQrData(
        voucher_code: 'PAY-1234',
        format: 'json',
        content_type: 'application/json',
        payload: [
            'type' => 'x-change.payment_qr',
        ],
        rendered: '{"type":"x-change.payment_qr"}',
    );

    $renderer = Mockery::mock(VoucherPaymentQrRendererContract::class);
    $renderer->shouldReceive('render')
        ->once()
        ->with($qr)
        ->andReturn($expected);

    $action = new RenderVoucherPaymentQr($renderer);

    expect($action->handle($qr))->toBe($expected);
});
