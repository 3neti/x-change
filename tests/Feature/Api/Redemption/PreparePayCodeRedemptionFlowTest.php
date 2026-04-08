<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\PreparePayCodeRedemptionFlow;
use LBHurtado\XChange\Data\Redemption\PrepareRedemptionResultData;
use LBHurtado\XChange\Data\Redemption\RedemptionFlowData;
use LBHurtado\XChange\Data\Redemption\RedemptionRequirementsData;
use LBHurtado\XChange\Data\Redemption\VoucherRedemptionProfileData;

it('returns redemption preparation metadata via api', function () {
    $voucher = issueVoucher(validVoucherInstructions());

    $result = new PrepareRedemptionResultData(
        voucher_code: $voucher->code,
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
            on_complete_callback: 'https://example.test/disburse/'.$voucher->code.'/complete',
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

    $action = Mockery::mock(PreparePayCodeRedemptionFlow::class);
    $action->shouldReceive('handle')
        ->once()
        ->with(Mockery::on(fn ($actual) => $actual instanceof Voucher && $actual->is($voucher)))
        ->andReturn($result);

    $this->app->instance(PreparePayCodeRedemptionFlow::class, $action);

    $response = $this->postJson(xchangeApi('pay-codes/'.$voucher->code.'/claim/start'));

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
            'data' => $result->toArray(),
            'meta' => [],
        ]);
});

it('returns not found when voucher code does not exist', function () {
    $response = $this->postJson(xchangeApi('pay-codes/DOES-NOT-EXIST/claim/start'));

    $response
        ->assertNotFound()
        ->assertJson([
            'success' => false,
            'message' => 'Invalid voucher code.',
            'code' => 'PAY_CODE_INVALID',
            'errors' => [
                'code' => ['Invalid voucher code.'],
            ],
        ]);
});
