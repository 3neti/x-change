<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\VoucherPaymentQrRendererContract;
use LBHurtado\XChange\Services\Base64PngVoucherPaymentQrRenderer;
use LBHurtado\XChange\Services\DefaultVoucherPaymentQrRenderer;
use LBHurtado\XChange\Services\VoucherPaymentQrRendererFactory;

it('resolves json payment qr renderer by default', function () {
    config()->set('x-change.payment_qr.renderer', 'json');

    $renderer = app(VoucherPaymentQrRendererContract::class);

    expect($renderer)->toBeInstanceOf(DefaultVoucherPaymentQrRenderer::class);
});

it('resolves png base64 payment qr renderer from config', function () {
    config()->set('x-change.payment_qr.renderer', 'png_base64');

    $renderer = app(VoucherPaymentQrRendererContract::class);

    expect($renderer)->toBeInstanceOf(Base64PngVoucherPaymentQrRenderer::class);
});

it('fails for unsupported payment qr renderer', function () {
    expect(fn () => app(VoucherPaymentQrRendererFactory::class)->make('missing'))
        ->toThrow(RuntimeException::class, 'Unsupported payment QR renderer [missing].');
});
