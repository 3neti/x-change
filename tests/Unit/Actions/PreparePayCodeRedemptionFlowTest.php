<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\PreparePayCodeRedemptionFlow;
use LBHurtado\XChange\Contracts\RedemptionFlowPreparationContract;
use LBHurtado\XChange\Data\Redemption\PrepareRedemptionResultData;
use LBHurtado\XChange\Data\Redemption\RedemptionFlowData;
use LBHurtado\XChange\Data\Redemption\RedemptionRequirementsData;
use LBHurtado\XChange\Data\Redemption\VoucherRedemptionProfileData;

it('delegates redemption flow preparation to the configured service', function () {
    $voucher = Mockery::mock(Voucher::class);

    $expected = new PrepareRedemptionResultData(
        voucher_code: 'TEST-1234',
        can_start: true,
        entry_route: 'disburse',
        profile: new VoucherRedemptionProfileData(
            instrument_kind: 'redeemable',
            redemption_mode: 'disburse',
            requires_form_flow: true,
            is_divisible: false,
            can_withdraw: false,
            slice_mode: null,
            driver_name: 'voucher-redemption',
        ),
        requirements: new RedemptionRequirementsData(
            required_inputs: ['selfie', 'signature'],
            required_validation: ['secret' => true],
            has_kyc: false,
            has_otp: false,
            has_location: false,
            has_selfie: true,
            has_signature: true,
            has_bio_fields: false,
        ),
        flow: new RedemptionFlowData(
            driver_name: 'voucher-redemption',
            driver_version: '1.0',
            reference_id_template: 'disburse-{{ code }}-{{ timestamp }}',
            on_complete_callback: 'https://example.test/disburse/TEST-1234/complete',
            on_cancel_callback: 'https://example.test/disburse',
            step_names: ['splash', 'wallet', 'selfie', 'signature'],
            step_handlers: [
                'splash' => 'splash',
                'wallet' => 'form',
                'selfie' => 'selfie',
                'signature' => 'signature',
            ],
            flow_instructions: null,
        ),
        messages: [],
    );

    $service = Mockery::mock(RedemptionFlowPreparationContract::class);
    $service->shouldReceive('prepare')
        ->once()
        ->with($voucher)
        ->andReturn($expected);

    $action = new PreparePayCodeRedemptionFlow($service);

    $result = $action->handle($voucher);

    expect($result)->toBeInstanceOf(PrepareRedemptionResultData::class);
    expect($result->toArray())->toBe($expected->toArray());
});
