<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\PreparePayCodeRedemptionFlow;
use LBHurtado\XChange\Contracts\RedemptionFlowPreparationContract;
use LBHurtado\XChange\Contracts\SettlementFlowPreparationContract;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Data\Redemption\PrepareRedemptionResultData;
use LBHurtado\XChange\Data\Redemption\RedemptionFlowData;
use LBHurtado\XChange\Data\Redemption\RedemptionRequirementsData;
use LBHurtado\XChange\Data\Redemption\VoucherRedemptionProfileData;
use LBHurtado\XChange\Data\Settlement\PrepareSettlementResultData;
use LBHurtado\XChange\Data\VoucherFlow\VoucherFlowCapabilitiesData;
use LBHurtado\XChange\Enums\VoucherFlowType;

function capabilityResolverReturning(VoucherFlowType $type): VoucherFlowCapabilityResolverContract
{
    $resolver = Mockery::mock(VoucherFlowCapabilityResolverContract::class);

    $resolver->shouldReceive('resolve')
        ->once()
        ->andReturn(new VoucherFlowCapabilitiesData(
            type: $type,
            label: $type->label(),
            direction: $type->direction(),
            can_disburse: $type !== VoucherFlowType::Collectible,
            can_collect: $type !== VoucherFlowType::Disbursable,
            can_settle: $type === VoucherFlowType::Settlement,
            supports_open_slices: $type === VoucherFlowType::Disbursable || $type === VoucherFlowType::Settlement,
            supports_delegated_spend: $type === VoucherFlowType::Disbursable,
            requires_envelope: $type === VoucherFlowType::Settlement,
            pay_code_route: match ($type) {
                VoucherFlowType::Disbursable => 'disburse',
                VoucherFlowType::Collectible => 'pay',
                VoucherFlowType::Settlement => 'settle',
            },
            qr_type: match ($type) {
                VoucherFlowType::Disbursable => 'claim',
                VoucherFlowType::Collectible => 'payment',
                VoucherFlowType::Settlement => 'settlement',
            },
        ));

    return $resolver;
}

it('delegates ordinary redemption preparation to the configured service', function () {
    $voucher = new Voucher;
    $voucher->code = 'TEST-1234';

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
            required_inputs: [],
            required_validation: [],
            has_kyc: false,
            has_otp: false,
            has_location: false,
            has_selfie: false,
            has_signature: false,
            has_bio_fields: false,
        ),
        flow: new RedemptionFlowData(
            driver_name: 'voucher-redemption',
            driver_version: '1.0',
            reference_id_template: 'disburse-{{ code }}-{{ timestamp }}',
            on_complete_callback: '/disburse/TEST-1234/complete',
            on_cancel_callback: '/disburse',
            step_names: ['splash', 'wallet'],
            step_handlers: [
                'splash' => 'splash',
                'wallet' => 'form',
            ],
            flow_instructions: null,
        ),
        messages: [],
    );

    $redemptionPreparation = Mockery::mock(RedemptionFlowPreparationContract::class);
    $settlementPreparation = Mockery::mock(SettlementFlowPreparationContract::class);

    $redemptionPreparation->shouldReceive('prepare')
        ->once()
        ->with($voucher)
        ->andReturn($expected);

    $settlementPreparation->shouldReceive('prepare')->never();

    $action = new PreparePayCodeRedemptionFlow(
        $redemptionPreparation,
        $settlementPreparation,
        capabilityResolverReturning(VoucherFlowType::Disbursable),
    );

    expect($action->handle($voucher))->toBe($expected);
});

it('routes settlement vouchers to settlement preparation service', function () {
    $voucher = new Voucher;
    $voucher->code = 'SETTLE-1234';

    $expected = new PrepareSettlementResultData(
        voucher_code: 'SETTLE-1234',
        can_start: false,
        entry_route: 'settle',
        requires_envelope: true,
        requirements: [
            'envelope' => true,
        ],
        capabilities: [
            'can_disburse' => true,
            'can_collect' => true,
            'can_settle' => true,
        ],
        messages: [
            'Settlement preparation is not yet implemented.',
        ],
    );

    $redemptionPreparation = Mockery::mock(RedemptionFlowPreparationContract::class);
    $settlementPreparation = Mockery::mock(SettlementFlowPreparationContract::class);

    $redemptionPreparation->shouldReceive('prepare')->never();

    $settlementPreparation->shouldReceive('prepare')
        ->once()
        ->with($voucher)
        ->andReturn($expected);

    $action = new PreparePayCodeRedemptionFlow(
        $redemptionPreparation,
        $settlementPreparation,
        capabilityResolverReturning(VoucherFlowType::Settlement),
    );

    expect($action->handle($voucher))->toBe($expected);
});
