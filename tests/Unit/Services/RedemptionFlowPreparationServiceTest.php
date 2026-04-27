<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\RedemptionFlowPreparationContract;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
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

    $flowResolver = app(VoucherFlowCapabilityResolverContract::class);
    $service = new DefaultRedemptionFlowPreparationService(new Container, $flowResolver);

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

    $flowResolver = app(VoucherFlowCapabilityResolverContract::class);
    $service = new DefaultRedemptionFlowPreparationService(new Container, $flowResolver);

    $result = $service->prepare($voucher);

    expect($result->can_start)->toBeTrue();
    expect($result->entry_route)->toBe('withdraw');
    expect($result->profile->redemption_mode)->toBe('withdraw');
    expect($result->profile->is_divisible)->toBeTrue();
    expect($result->profile->can_withdraw)->toBeTrue();
    expect($result->profile->slice_mode)->toBe('open');
});

it('rejects collectible vouchers for outward redemption preparation', function () {
    $voucher = new Voucher;
    $voucher->setAttribute('metadata', [
        'flow_type' => 'collectible',
    ]);

    $service = app(RedemptionFlowPreparationContract::class);

    expect(fn () => $service->prepare($voucher, []))
        ->toThrow(RuntimeException::class, 'cannot prepare outward claim flow');
});

it('rejects legacy payable vouchers for outward redemption preparation', function () {
    $voucher = new Voucher;
    $voucher->setAttribute('metadata', [
        'voucher_type' => 'payable',
    ]);

    $service = app(RedemptionFlowPreparationContract::class);

    expect(fn () => $service->prepare($voucher, []))
        ->toThrow(RuntimeException::class, 'cannot prepare outward claim flow');
});

it('rejects settlement vouchers from ordinary outward redemption preparation', function () {
    $voucher = issueVoucher(validVoucherInstructions());

    $flowResolver = Mockery::mock(VoucherFlowCapabilityResolverContract::class);

    $flowResolver->shouldReceive('resolve')
        ->once()
        ->with($voucher)
        ->andReturn(new \LBHurtado\XChange\Data\VoucherFlow\VoucherFlowCapabilitiesData(
            type: \LBHurtado\XChange\Enums\VoucherFlowType::Settlement,
            label: 'Settlement Voucher',
            direction: 'both',
            can_disburse: true,
            can_collect: true,
            can_settle: true,
            supports_open_slices: true,
            supports_delegated_spend: false,
            requires_envelope: true,
            pay_code_route: 'settle',
            qr_type: 'settlement',
        ));

    $service = new DefaultRedemptionFlowPreparationService(
        new Container,
        $flowResolver,
    );

    expect(fn () => $service->prepare($voucher))
        ->toThrow(RuntimeException::class, 'Settlement vouchers cannot prepare ordinary outward claim flow');
});

it('allows legacy redeemable vouchers for outward redemption preparation', function () {
    $voucher = issueVoucher(validVoucherInstructions(100.00, 'INSTAPAY', [
        'voucher_type' => 'redeemable',
    ]));

    $service = app(\LBHurtado\XChange\Contracts\RedemptionFlowPreparationContract::class);

    $result = $service->prepare($voucher, []);

    expect($result)->not->toBeNull();
});
