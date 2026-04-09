<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\WithdrawalProcessorContract;
use LBHurtado\XChange\Contracts\WithdrawalValidationContract;
use LBHurtado\XChange\Data\Redemption\WithdrawPayCodeResultData;
use LBHurtado\XChange\Services\DefaultWithdrawalExecutionService;

it('validates and processes withdrawal through package services', function () {
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

    $resultDto = new WithdrawPayCodeResultData(
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

    $validator = Mockery::mock(WithdrawalValidationContract::class);
    $validator->shouldReceive('validate')
        ->once()
        ->with($voucher, $payload);

    $processor = Mockery::mock(WithdrawalProcessorContract::class);
    $processor->shouldReceive('process')
        ->once()
        ->with($voucher, $payload)
        ->andReturn($resultDto);

    $service = new DefaultWithdrawalExecutionService($validator, $processor);

    $result = $service->withdraw($voucher, $payload);

    expect($result)->toBeInstanceOf(WithdrawPayCodeResultData::class);
    expect($result->toArray())->toBe($resultDto->toArray());
});
