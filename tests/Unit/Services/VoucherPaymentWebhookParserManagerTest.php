<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\VoucherPaymentWebhookParserContract;
use LBHurtado\XChange\Data\Payment\VoucherPaymentResultData;
use LBHurtado\XChange\Services\PaymentWebhooks\ManualVoucherPaymentWebhookParser;
use LBHurtado\XChange\Services\VoucherPaymentWebhookParserManager;

it('resolves manual webhook parser by default', function () {
    config()->set('x-change.payment.default_provider', 'manual');

    $parser = app(VoucherPaymentWebhookParserManager::class)->driver();

    expect($parser)->toBeInstanceOf(ManualVoucherPaymentWebhookParser::class);
});

it('resolves configured custom webhook parser', function () {
    config()->set('x-change.payment.webhook_parsers.fake', FakeVoucherPaymentWebhookParser::class);

    $parser = app(VoucherPaymentWebhookParserManager::class)->driver('fake');

    expect($parser)->toBeInstanceOf(FakeVoucherPaymentWebhookParser::class);
});

it('fails for unsupported webhook parser', function () {
    app(VoucherPaymentWebhookParserManager::class)->driver('missing');
})->throws(RuntimeException::class);

class FakeVoucherPaymentWebhookParser implements VoucherPaymentWebhookParserContract
{
    public function parse(array $payload): VoucherPaymentResultData
    {
        return new VoucherPaymentResultData(
            voucher_code: (string) ($payload['voucher_code'] ?? ''),
            status: 'succeeded',
            amount: 1.00,
            provider: 'fake',
        );
    }

    public function voucherCode(array $payload): ?string
    {
        return $payload['voucher_code'] ?? null;
    }
}
