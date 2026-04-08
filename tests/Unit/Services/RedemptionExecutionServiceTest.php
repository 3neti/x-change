<?php

declare(strict_types=1);

use LBHurtado\Voucher\Data\RedemptionContext;
use LBHurtado\XChange\Contracts\RedemptionContextResolverContract;
use LBHurtado\XChange\Contracts\RedemptionProcessorContract;
use LBHurtado\XChange\Contracts\RedemptionValidationContract;
use LBHurtado\XChange\Data\Redemption\RedeemPayCodeResultData;
use LBHurtado\XChange\Services\DefaultRedemptionExecutionService;

it('resolves context validates and processes redemption through package services', function () {
    $voucher = issueVoucher(validVoucherInstructions());

    $payload = [
        'mobile' => '09171234567',
        'recipient_country' => 'PH',
        'secret' => '1234',
        'inputs' => [
            'name' => 'Juan Dela Cruz',
            'otp' => '123456',
        ],
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ],
    ];

    $context = new RedemptionContext(
        mobile: '09171234567',
        secret: '1234',
        vendorAlias: null,
        inputs: [
            'name' => 'Juan Dela Cruz',
            'otp' => '123456',
        ],
        bankAccount: [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ],
    );

    $resolver = Mockery::mock(RedemptionContextResolverContract::class);
    $resolver->shouldReceive('resolve')
        ->once()
        ->with($payload)
        ->andReturn($context);

    $validator = Mockery::mock(RedemptionValidationContract::class);
    $validator->shouldReceive('validate')
        ->once()
        ->with($voucher, $context);

    $processor = Mockery::mock(RedemptionProcessorContract::class);
    $processor->shouldReceive('process')
        ->once()
        ->with($voucher, $context)
        ->andReturn(true);

    $service = new DefaultRedemptionExecutionService(
        $resolver,
        $validator,
        $processor,
    );

    $result = $service->redeem($voucher, $payload);

    expect($result)->toBeInstanceOf(RedeemPayCodeResultData::class);
    expect($result->voucher_code)->toBe($voucher->code);
    expect($result->redeemed)->toBeTrue();
    expect($result->status)->toBe('redeemed');
    expect($result->redeemer)->toBe([
        'mobile' => '09171234567',
        'country' => 'PH',
    ]);
    expect($result->bank_account)->toBe([
        'bank_code' => 'GXCHPHM2XXX',
        'account_number' => '09171234567',
    ]);
    expect($result->inputs)->toBe([
        'name' => 'Juan Dela Cruz',
        'otp' => '123456',
    ]);
    expect($result->disbursement)->toBe([
        'status' => 'requested',
    ]);
});
