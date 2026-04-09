<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\WithdrawPayCode;
use LBHurtado\XChange\Contracts\WithdrawalExecutionContract;
use LBHurtado\XChange\Data\Redemption\WithdrawPayCodeResultData;

it('delegates withdrawal execution to the configured service', function () {
    $voucher = Mockery::mock(Voucher::class);

    $payload = [
        'mobile' => '09171234567',
        'recipient_country' => 'PH',
        'amount' => 200.00,
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ],
    ];

    $expected = new WithdrawPayCodeResultData(
        voucher_code: 'TEST-1234',
        withdrawn: true,
        status: 'withdrawn',
        requested_amount: 200.00,
        disbursed_amount: 200.00,
        currency: 'PHP',
        remaining_balance: 300.00,
        slice_number: 2,
        remaining_slices: 3,
        slice_mode: 'open',
        redeemer: [
            'mobile' => '09171234567',
            'country' => 'PH',
        ],
        bank_account: [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ],
        disbursement: [
            'status' => 'requested',
        ],
        messages: ['Voucher withdrawal successful.'],
    );

    $service = Mockery::mock(WithdrawalExecutionContract::class);
    $service->shouldReceive('withdraw')
        ->once()
        ->with($voucher, $payload)
        ->andReturn($expected);

    $action = new WithdrawPayCode($service);

    $result = $action->handle($voucher, $payload);

    expect($result)->toBeInstanceOf(WithdrawPayCodeResultData::class);
    expect($result->toArray())->toBe($expected->toArray());
});
