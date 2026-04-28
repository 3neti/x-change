<?php

declare(strict_types=1);

use LBHurtado\XChange\Data\Payment\VoucherPaymentQrData;
use LBHurtado\XChange\Services\Base64PngVoucherPaymentQrRenderer;

it('renders payment qr payload as base64 png data uri shape', function () {
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

    $result = app(Base64PngVoucherPaymentQrRenderer::class)->render($qr);

    expect($result->voucher_code)->toBe('PAY-1234');
    expect($result->format)->toBe('png_base64');
    expect($result->content_type)->toBe('image/png');
    expect($result->payload['type'])->toBe('x-change.payment_qr');
    expect($result->rendered)->toStartWith('data:image/png;base64,');

    $encoded = substr($result->rendered, strlen('data:image/png;base64,'));
    $decoded = base64_decode($encoded, true);

    expect($decoded)->not->toBeFalse();
    expect($decoded)->toContain('PAY-1234');
});
