<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Contracts\ClaimExecutionFactoryContract;
use LBHurtado\XChange\Contracts\ClaimExecutorContract;
use LBHurtado\XChange\Data\Redemption\RedeemPayCodeResultData;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;

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

    $action = new SubmitPayCodeClaim($factory);

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

    $action = new SubmitPayCodeClaim($factory);

    expect(fn () => $action->handle($voucher, $payload))
        ->toThrow(RuntimeException::class, 'Unsupported claim execution result type');
});
