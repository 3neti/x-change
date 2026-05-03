<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Payment\CollectVoucherFunds;
use LBHurtado\XChange\Actions\Settlement\CollectSettlementPayment;
use LBHurtado\XChange\Data\Payment\VoucherPaymentResultData;

it('collects settlement payment through the generic voucher collection action', function () {
    $voucher = new Voucher;

    $payload = [
        'provider' => 'manual',
        'provider_reference' => 'PHILHEALTH-CLAIM-001',
        'amount' => 20000,
        'currency' => 'PHP',
        'status' => 'succeeded',
    ];

    $collectVoucherFunds = Mockery::mock(CollectVoucherFunds::class);
    $collectVoucherFunds
        ->shouldReceive('handle')
        ->once()
        ->with($voucher, $payload)
        ->andReturn(new VoucherPaymentResultData(
            voucher_code: 'TEST-SETTLE',
            status: 'collected',
            amount: 20000,
            currency: 'PHP',
        ));

    $result = app()->makeWith(CollectSettlementPayment::class, [
        'collectVoucherFunds' => $collectVoucherFunds,
    ])->handle($voucher, $payload);

    expect($result->status)->toBe('collected')
        ->and($result->amount)->toBe(20000.0);
});
