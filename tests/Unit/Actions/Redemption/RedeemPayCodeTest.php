<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\RedeemPayCode;
use LBHurtado\XChange\Contracts\RedemptionExecutionContract;
use LBHurtado\XChange\Data\Redemption\RedeemPayCodeResultData;

it('delegates redemption execution to the configured service', function () {
    $voucher = Mockery::mock(Voucher::class);

    $payload = [
        'mobile' => '09171234567',
        'recipient_country' => 'PH',
        'secret' => '1234',
        'inputs' => [
            'name' => 'Juan Dela Cruz',
        ],
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ],
    ];

    $expected = new RedeemPayCodeResultData(
        voucher_code: 'TEST-1234',
        redeemed: true,
        status: 'redeemed',
        redeemer: [
            'mobile' => '09171234567',
            'country' => 'PH',
        ],
        bank_account: [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ],
        inputs: [
            'name' => 'Juan Dela Cruz',
        ],
        disbursement: [
            'status' => 'requested',
        ],
        messages: ['Voucher redeemed successfully.'],
    );

    $service = Mockery::mock(RedemptionExecutionContract::class);
    $service->shouldReceive('redeem')
        ->once()
        ->with($voucher, $payload)
        ->andReturn($expected);

    $action = new RedeemPayCode($service);

    $result = $action->handle($voucher, $payload);

    expect($result)->toBeInstanceOf(RedeemPayCodeResultData::class);
    expect($result->toArray())->toBe($expected->toArray());
});
