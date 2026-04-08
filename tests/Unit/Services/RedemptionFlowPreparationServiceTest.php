<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use LBHurtado\XChange\Data\Redemption\PrepareRedemptionResultData;
use LBHurtado\XChange\Services\DefaultRedemptionFlowPreparationService;

it('prepares redemption flow metadata from voucher instructions', function () {
    $instructions = validVoucherInstructions(100.00, 'INSTAPAY', [
        'cash' => [
            'validation' => [
                'secret' => '1234',
                'mobile' => '09171234567',
                'payable' => null,
                'location' => null,
                'radius' => null,
            ],
        ],
        'inputs' => [
            'fields' => [
                'kyc',
                'selfie',
                'signature',
                'name',
                'email',
            ],
        ],
        'rider' => [
            'splash' => '<h1>Hello</h1>',
            'splash_timeout' => 3,
        ],
    ]);

    $voucher = issueVoucher($instructions);

    $service = new DefaultRedemptionFlowPreparationService(new Container);

    $result = $service->prepare($voucher);

    expect($result)->toBeInstanceOf(PrepareRedemptionResultData::class);

    expect($result->voucher_code)->toBe($voucher->code);
    expect($result->can_start)->toBeTrue();
    expect($result->entry_route)->toBe('disburse');

    expect($result->profile->instrument_kind)->toBe('redeemable');
    expect($result->profile->redemption_mode)->toBe('disburse');
    expect($result->profile->requires_form_flow)->toBeTrue();
    expect($result->profile->is_divisible)->toBeFalse();
    expect($result->profile->can_withdraw)->toBeFalse();
    expect($result->profile->slice_mode)->toBeNull();
    expect($result->profile->driver_name)->toBe('voucher-redemption');

    expect($result->requirements->required_inputs)->toBe([
        'kyc',
        'selfie',
        'signature',
        'name',
        'email',
    ]);

    expect($result->requirements->required_validation)->toMatchArray([
        'secret' => true,
        'mobile' => '09171234567',
    ]);

    expect($result->requirements->has_kyc)->toBeTrue();
    expect($result->requirements->has_selfie)->toBeTrue();
    expect($result->requirements->has_signature)->toBeTrue();
    expect($result->requirements->has_bio_fields)->toBeTrue();
    expect($result->requirements->has_otp)->toBeFalse();
    expect($result->requirements->has_location)->toBeFalse();

    expect($result->flow->driver_name)->toBe('voucher-redemption');
    expect($result->flow->driver_version)->toBe('1.0');
    expect($result->flow->reference_id_template)->toBe('disburse-{{ code }}-{{ timestamp }}');
    expect($result->flow->on_complete_callback)->toContain('/disburse/'.$voucher->code.'/complete');
    expect($result->flow->on_cancel_callback)->toContain('/disburse');

    expect($result->flow->step_names)->toBe([
        'splash',
        'wallet',
        'kyc',
        'bio',
        'selfie',
        'signature',
    ]);

    expect($result->flow->step_handlers)->toBe([
        'splash' => 'splash',
        'wallet' => 'form',
        'kyc' => 'kyc',
        'bio' => 'form',
        'selfie' => 'selfie',
        'signature' => 'signature',
    ]);

    expect($result->flow->flow_instructions)->toBeNull();
    expect($result->messages)->toBe([]);
});

it('routes redeemed withdrawable vouchers to withdraw entry', function () {
    $voucher = Mockery::mock(issueVoucher(validVoucherInstructions()))->makePartial();

    $voucher->shouldReceive('isRedeemed')->andReturn(true);
    $voucher->shouldReceive('isExpired')->andReturn(false);
    $voucher->shouldReceive('canWithdraw')->andReturn(true);
    $voucher->shouldReceive('getSliceMode')->andReturn('open');

    $service = new DefaultRedemptionFlowPreparationService(new Container);

    $result = $service->prepare($voucher);

    expect($result->can_start)->toBeTrue();
    expect($result->entry_route)->toBe('withdraw');
    expect($result->profile->redemption_mode)->toBe('withdraw');
    expect($result->profile->is_divisible)->toBeTrue();
    expect($result->profile->can_withdraw)->toBeTrue();
    expect($result->profile->slice_mode)->toBe('open');
});
