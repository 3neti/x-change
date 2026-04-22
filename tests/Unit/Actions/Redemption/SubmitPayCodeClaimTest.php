<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\RecordVoucherClaim;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Contracts\ClaimExecutionFactoryContract;
use LBHurtado\XChange\Contracts\ClaimExecutorContract;
use LBHurtado\XChange\Data\Redemption\RedeemPayCodeResultData;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Data\Redemption\WithdrawPayCodeResultData;
use LBHurtado\XChange\Models\VoucherClaim;

it('submits a claim through the selected executor and normalizes redeem result', function () {
    $voucher = Mockery::mock(Voucher::class);

    $payload = [
        'mobile' => '09171234567',
        'recipient_country' => 'PH',
        'secret' => '1234',
        'amount' => 100.00,
        'inputs' => [
            'name' => 'Juan Dela Cruz',
        ],
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ],
    ];

    $executorResult = new RedeemPayCodeResultData(
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

    $executor = Mockery::mock(ClaimExecutorContract::class);
    $executor->shouldReceive('handle')
        ->once()
        ->with($voucher, $payload)
        ->andReturn($executorResult);

    $factory = Mockery::mock(ClaimExecutionFactoryContract::class);
    $factory->shouldReceive('make')
        ->once()
        ->with($voucher, $payload)
        ->andReturn($executor);

    $recordVoucherClaim = Mockery::mock(RecordVoucherClaim::class);
    $recordVoucherClaim
        ->shouldReceive('handle')
        ->once()
        ->with($voucher, Mockery::type(SubmitPayCodeClaimResultData::class), $payload)
        ->andReturn(new VoucherClaim);

    $action = new SubmitPayCodeClaim($factory, $recordVoucherClaim);

    $result = $action->handle($voucher, $payload);

    expect($result)->toBeInstanceOf(SubmitPayCodeClaimResultData::class);
    expect($result->voucher_code)->toBe('TEST-1234');
    expect($result->claim_type)->toBe('redeem');
    expect($result->claimed)->toBeTrue();
    expect($result->status)->toBe('redeemed');
    expect($result->requested_amount)->toBe(100.0);
    expect($result->disbursed_amount)->toBeNull();
    expect($result->currency)->toBeNull();
    expect($result->remaining_balance)->toBeNull();
    expect($result->fully_claimed)->toBeTrue();
    expect($result->disbursement)->toBe([
        'status' => 'requested',
    ]);
    expect($result->messages)->toBe([
        'Voucher redeemed successfully.',
    ]);
});

it('submits a claim through the selected executor and normalizes withdraw result', function () {
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

    $executorResult = new WithdrawPayCodeResultData(
        voucher_code: 'TEST-9999',
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

    $executor = Mockery::mock(ClaimExecutorContract::class);
    $executor->shouldReceive('handle')
        ->once()
        ->with($voucher, $payload)
        ->andReturn($executorResult);

    $factory = Mockery::mock(ClaimExecutionFactoryContract::class);
    $factory->shouldReceive('make')
        ->once()
        ->with($voucher, $payload)
        ->andReturn($executor);

    $recordVoucherClaim = Mockery::mock(RecordVoucherClaim::class);
    $recordVoucherClaim
        ->shouldReceive('handle')
        ->once()
        ->with($voucher, Mockery::type(SubmitPayCodeClaimResultData::class), $payload)
        ->andReturn(new VoucherClaim);

    $action = new SubmitPayCodeClaim($factory, $recordVoucherClaim);

    $result = $action->handle($voucher, $payload);

    expect($result)->toBeInstanceOf(SubmitPayCodeClaimResultData::class);
    expect($result->voucher_code)->toBe('TEST-9999');
    expect($result->claim_type)->toBe('withdraw');
    expect($result->claimed)->toBeTrue();
    expect($result->status)->toBe('withdrawn');
    expect($result->requested_amount)->toBe(200.0);
    expect($result->disbursed_amount)->toBe(200.0);
    expect($result->currency)->toBe('PHP');
    expect($result->remaining_balance)->toBe(300.0);
    expect($result->fully_claimed)->toBeFalse();
    expect($result->disbursement)->toBe([
        'status' => 'requested',
    ]);
    expect($result->messages)->toBe([
        'Voucher withdrawal successful.',
    ]);
});

it('throws for unsupported executor result types', function () {
    $voucher = Mockery::mock(Voucher::class);

    $payload = [
        'mobile' => '09171234567',
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ],
        'inputs' => [],
    ];

    $executor = Mockery::mock(ClaimExecutorContract::class);
    $executor->shouldReceive('handle')
        ->once()
        ->with($voucher, $payload)
        ->andReturn((object) ['unexpected' => true]);

    $factory = Mockery::mock(ClaimExecutionFactoryContract::class);
    $factory->shouldReceive('make')
        ->once()
        ->with($voucher, $payload)
        ->andReturn($executor);

    $recordVoucherClaim = Mockery::mock(RecordVoucherClaim::class);
    $recordVoucherClaim->shouldNotReceive('handle');

    $action = new SubmitPayCodeClaim($factory, $recordVoucherClaim);

    expect(fn () => $action->handle($voucher, $payload))
        ->toThrow(RuntimeException::class, 'Unsupported claim execution result type');
});
