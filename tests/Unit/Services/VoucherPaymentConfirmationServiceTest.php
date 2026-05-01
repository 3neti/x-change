<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\VoucherPaymentProviderContract;
use LBHurtado\XChange\Data\Payment\VoucherPaymentResultData;
use LBHurtado\XChange\Services\DefaultVoucherPaymentConfirmationService;

it('delegates payment confirmation to selected provider', function () {
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

    $provider = Mockery::mock(VoucherPaymentProviderContract::class);
    $provider->shouldReceive('confirm')
        ->once()
        ->with($voucher, Mockery::on(fn (array $payload) => $payload['provider'] === 'fake'))
        ->andReturn(new VoucherPaymentResultData(
            voucher_code: $voucher->code,
            status: 'succeeded',
            amount: 100.00,
            provider: 'fake',
        ));

    config()->set('x-change.payment.providers.fake', get_class($provider));

    app()->instance(get_class($provider), $provider);

    $result = app(DefaultVoucherPaymentConfirmationService::class)->confirm($voucher, [
        'amount' => 100.00,
        'provider' => 'fake',
    ]);

    expect($result->status)->toBe('succeeded')
        ->and($result->provider)->toBe('fake');
});
