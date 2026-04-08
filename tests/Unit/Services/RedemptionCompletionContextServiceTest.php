<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\RedemptionCompletionStoreContract;
use LBHurtado\XChange\Data\Redemption\LoadRedemptionCompletionContextResultData;
use LBHurtado\XChange\Services\DefaultRedemptionCompletionContextService;

it('loads redemption completion context from stored flow state', function () {
    $voucher = issueVoucher(validVoucherInstructions());

    $store = Mockery::mock(RedemptionCompletionStoreContract::class);
    $store->shouldReceive('findByReference')
        ->once()
        ->with('disburse-'.$voucher->code.'-123')
        ->andReturn([
            'flow_id' => 'flow-123',
            'collected_data' => [
                'wallet_info' => [
                    'amount' => 100.0,
                    'settlement_rail' => 'INSTAPAY',
                    'mobile' => '09171234567',
                    'recipient_country' => 'PH',
                    'bank_code' => 'GXCHPHM2XXX',
                    'account_number' => '09171234567',
                ],
                'bio_fields' => [
                    'full_name' => 'Juan Dela Cruz',
                    'email' => 'juan@example.com',
                    'date_of_birth' => '1990-01-01',
                ],
                'otp_verification' => [
                    'otp_code' => '123456',
                ],
            ],
        ]);

    $service = new DefaultRedemptionCompletionContextService($store);

    $result = $service->load($voucher, 'disburse-'.$voucher->code.'-123', null);

    expect($result)->toBeInstanceOf(LoadRedemptionCompletionContextResultData::class);
    expect($result->voucher_code)->toBe($voucher->code);
    expect($result->can_confirm)->toBeTrue();
    expect($result->reference_id)->toBe('disburse-'.$voucher->code.'-123');
    expect($result->flow_id)->toBe('flow-123');

    expect($result->wallet)->toMatchArray([
        'amount' => 100.0,
        'settlement_rail' => 'INSTAPAY',
        'mobile' => '09171234567',
        'recipient_country' => 'PH',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number' => '09171234567',
    ]);

    expect($result->flat_data)->toMatchArray([
        'name' => 'Juan Dela Cruz',
        'email' => 'juan@example.com',
        'birth_date' => '1990-01-01',
        'otp' => '123456',
    ]);

    expect($result->inputs)->toMatchArray([
        'name' => 'Juan Dela Cruz',
        'email' => 'juan@example.com',
        'birth_date' => '1990-01-01',
        'otp' => '123456',
    ]);

    expect($result->messages)->toBe([]);
});

it('returns non-confirmable result when completion state is missing', function () {
    $voucher = issueVoucher(validVoucherInstructions());

    $store = Mockery::mock(RedemptionCompletionStoreContract::class);
    $store->shouldReceive('findByReference')
        ->once()
        ->with('missing-ref')
        ->andReturn(null);

    $service = new DefaultRedemptionCompletionContextService($store);

    $result = $service->load($voucher, 'missing-ref', null);

    expect($result->can_confirm)->toBeFalse();
    expect($result->collected_data)->toBe([]);
    expect($result->flat_data)->toBe([]);
    expect($result->wallet)->toBe([]);
    expect($result->inputs)->toBe([]);
    expect($result->messages)->toBe(['Session expired. Please try again.']);
});
