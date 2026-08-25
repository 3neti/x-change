<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\VoucherPaymentProviderContract;
use LBHurtado\XChange\Services\PaymentProviders\ManualVoucherPaymentProvider;
use LBHurtado\XChange\Services\PaymentProviders\NullVoucherPaymentProvider;
use LBHurtado\XChange\Services\VoucherPaymentProviderManager;

it('resolves manual provider by default', function () {
    config()->set('x-change.payment.default_provider', 'manual');

    $provider = app(VoucherPaymentProviderManager::class)->driver();

    expect($provider)->toBeInstanceOf(ManualVoucherPaymentProvider::class);
});

it('resolves null provider', function () {
    $provider = app(VoucherPaymentProviderManager::class)->driver('null');

    expect($provider)->toBeInstanceOf(NullVoucherPaymentProvider::class);
});

it('blocks manual provider outside local and testing unless explicitly allowed', function () {
    config()->set('app.env', 'production');
    config()->set('x-change.payment.allow_manual_provider_in_production', false);

    app(VoucherPaymentProviderManager::class)->driver('manual');
})->throws(RuntimeException::class, 'Manual voucher payment provider is disabled');

it('allows manual provider outside local and testing when explicitly configured', function () {
    config()->set('app.env', 'production');
    config()->set('x-change.payment.allow_manual_provider_in_production', true);

    $provider = app(VoucherPaymentProviderManager::class)->driver('manual');

    expect($provider)->toBeInstanceOf(ManualVoucherPaymentProvider::class);
});

it('resolves configured custom provider', function () {
    config()->set('x-change.payment.providers.fake', FakeVoucherPaymentProvider::class);

    $provider = app(VoucherPaymentProviderManager::class)->driver('fake');

    expect($provider)->toBeInstanceOf(FakeVoucherPaymentProvider::class);
});

it('fails for unsupported provider', function () {
    app(VoucherPaymentProviderManager::class)->driver('missing');
})->throws(RuntimeException::class);

class FakeVoucherPaymentProvider implements VoucherPaymentProviderContract
{
    public function confirm(
        \LBHurtado\Voucher\Models\Voucher $voucher,
        array $payload
    ): \LBHurtado\XChange\Data\Payment\VoucherPaymentResultData {
        return new \LBHurtado\XChange\Data\Payment\VoucherPaymentResultData(
            voucher_code: $voucher->code,
            status: 'succeeded',
            amount: 1.00,
            provider: 'fake',
        );
    }
}
