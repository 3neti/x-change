<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use LBHurtado\XChange\Actions\Redemption\RedeemPayCode;
use LBHurtado\XChange\Actions\Redemption\WithdrawPayCode;
use LBHurtado\XChange\Contracts\SettlementExecutionContract;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Data\VoucherFlow\VoucherFlowCapabilitiesData;
use LBHurtado\XChange\Enums\VoucherFlowType;
use LBHurtado\XChange\Services\DefaultClaimExecutionFactory;

function disbursableFlowResolver(): VoucherFlowCapabilityResolverContract
{
    $resolver = Mockery::mock(VoucherFlowCapabilityResolverContract::class);

    $resolver->shouldReceive('resolve')
        ->byDefault()
        ->andReturn(new VoucherFlowCapabilitiesData(
            type: VoucherFlowType::Disbursable,
            label: 'Cash Out Voucher',
            direction: 'outward',
            can_disburse: true,
            can_collect: false,
            can_settle: false,
            supports_open_slices: true,
            supports_delegated_spend: true,
            requires_envelope: false,
            pay_code_route: 'disburse',
            qr_type: 'claim',
        ));

    return $resolver;
}

function flowResolverFor(VoucherFlowType $type, bool $canDisburse): \LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract
{
    $resolver = Mockery::mock(\LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract::class);

    $resolver->shouldReceive('resolve')
        ->byDefault()
        ->andReturn(new \LBHurtado\XChange\Data\VoucherFlow\VoucherFlowCapabilitiesData(
            type: $type,
            label: $type->label(),
            direction: $type->direction(),
            can_disburse: $canDisburse,
            can_collect: $type === VoucherFlowType::Collectible || $type === VoucherFlowType::Settlement,
            can_settle: $type === VoucherFlowType::Settlement,
            supports_open_slices: $type !== VoucherFlowType::Collectible,
            supports_delegated_spend: $type !== VoucherFlowType::Collectible,
            requires_envelope: $type === VoucherFlowType::Settlement,
            pay_code_route: match ($type) {
                VoucherFlowType::Disbursable => 'disburse',
                VoucherFlowType::Collectible => 'pay',
                VoucherFlowType::Settlement => 'settle',
            },
            qr_type: match ($type) {
                VoucherFlowType::Disbursable => 'claim',
                VoucherFlowType::Collectible => 'payment',
                VoucherFlowType::Settlement => 'hybrid',
            },
        ));

    return $resolver;
}

it('returns redeem executor for a normal voucher', function () {
    $voucher = Mockery::mock(\LBHurtado\Voucher\Models\Voucher::class);
    $voucher->shouldReceive('isDivisible')->once()->andReturn(false);
    $voucher->shouldReceive('isRedeemed')->once()->andReturn(false);
    $voucher->shouldReceive('canWithdraw')->never();
    $voucher->shouldReceive('getSliceMode')->never();

    $redeemExecutor = Mockery::mock(RedeemPayCode::class);

    $container = new Container;

    $factory = new DefaultClaimExecutionFactory(
        $container,
        $redeemExecutor,
        disbursableFlowResolver(),
    );

    $result = $factory->make($voucher, []);

    expect($result)->toBe($redeemExecutor);
});

it('returns withdraw executor when voucher is withdrawable', function () {
    $voucher = Mockery::mock(\LBHurtado\Voucher\Models\Voucher::class);
    $voucher->shouldReceive('isDivisible')->once()->andReturn(false);
    $voucher->shouldReceive('isRedeemed')->once()->andReturn(true);
    $voucher->shouldReceive('canWithdraw')->once()->andReturn(true);
    $voucher->shouldReceive('getSliceMode')->never();

    $redeemExecutor = Mockery::mock(RedeemPayCode::class);
    $withdrawExecutor = Mockery::mock(WithdrawPayCode::class);

    $container = new Container;
    $container->instance(WithdrawPayCode::class, $withdrawExecutor);

    $factory = new DefaultClaimExecutionFactory(
        $container,
        $redeemExecutor,
        disbursableFlowResolver(),
    );

    $result = $factory->make($voucher, []);

    expect($result)->toBe($withdrawExecutor);
});

it('returns redeem executor when voucher is redeemed but not withdrawable', function () {
    $voucher = Mockery::mock(\LBHurtado\Voucher\Models\Voucher::class);
    $voucher->shouldReceive('isDivisible')->once()->andReturn(false);
    $voucher->shouldReceive('isRedeemed')->once()->andReturn(true);
    $voucher->shouldReceive('canWithdraw')->once()->andReturn(false);

    $redeemExecutor = Mockery::mock(RedeemPayCode::class);
    $withdrawExecutor = Mockery::mock(WithdrawPayCode::class);

    $container = new Container;
    $container->instance(WithdrawPayCode::class, $withdrawExecutor);

    $factory = new DefaultClaimExecutionFactory(
        $container,
        $redeemExecutor,
        disbursableFlowResolver(),
    );

    $result = $factory->make($voucher, []);

    expect($result)->toBe($redeemExecutor);
});

it('rejects collectible vouchers for outward claim execution', function () {
    $voucher = new \LBHurtado\Voucher\Models\Voucher;
    $voucher->setAttribute('metadata', [
        'flow_type' => 'collectible',
    ]);

    $factory = app(\LBHurtado\XChange\Contracts\ClaimExecutionFactoryContract::class);

    expect(fn () => $factory->make($voucher, []))
        ->toThrow(RuntimeException::class, 'cannot execute outward claims');
});

it('routes open-slice disbursable vouchers to withdrawal executor', function () {
    $voucher = Mockery::mock(\LBHurtado\Voucher\Models\Voucher::class);

    $voucher->shouldReceive('isDivisible')->once()->andReturn(true);
    $voucher->shouldReceive('getSliceMode')->once()->andReturn('open');
    $voucher->shouldReceive('isRedeemed')->never();
    $voucher->shouldReceive('canWithdraw')->never();

    $redeemExecutor = Mockery::mock(RedeemPayCode::class);
    $withdrawExecutor = Mockery::mock(WithdrawPayCode::class);

    $container = new Container;
    $container->instance(WithdrawPayCode::class, $withdrawExecutor);

    $factory = new DefaultClaimExecutionFactory(
        $container,
        $redeemExecutor,
        disbursableFlowResolver(),
    );

    $result = $factory->make($voucher, []);

    expect($result)->toBe($withdrawExecutor);
});

//it('does not allow settlement vouchers to blindly execute outward claims yet', function () {
//    $voucher = Mockery::mock(\LBHurtado\Voucher\Models\Voucher::class);
//
//    $voucher->shouldReceive('isDivisible')->never();
//    $voucher->shouldReceive('getSliceMode')->never();
//    $voucher->shouldReceive('isRedeemed')->never();
//    $voucher->shouldReceive('canWithdraw')->never();
//
//    $redeemExecutor = Mockery::mock(RedeemPayCode::class);
//
//    $container = new Container;
//
//    $factory = new DefaultClaimExecutionFactory(
//        $container,
//        $redeemExecutor,
//        flowResolverFor(VoucherFlowType::Settlement, canDisburse: false),
//    );
//
//    expect(fn () => $factory->make($voucher, []))
//        ->toThrow(RuntimeException::class, 'cannot execute outward claims');
//});

it('rejects legacy payable vouchers for outward claim execution', function () {
    $voucher = new \LBHurtado\Voucher\Models\Voucher;
    $voucher->setAttribute('metadata', [
        'voucher_type' => 'payable',
    ]);

    $factory = app(\LBHurtado\XChange\Contracts\ClaimExecutionFactoryContract::class);

    expect(fn () => $factory->make($voucher, []))
        ->toThrow(RuntimeException::class, 'cannot execute outward claims');
});

it('allows legacy redeemable vouchers for outward claim execution', function () {
    $voucher = Mockery::mock(\LBHurtado\Voucher\Models\Voucher::class);

    $voucher->shouldReceive('isDivisible')->once()->andReturn(false);
    $voucher->shouldReceive('isRedeemed')->once()->andReturn(false);
    $voucher->shouldReceive('canWithdraw')->never();
    $voucher->shouldReceive('getSliceMode')->never();

    $redeemExecutor = Mockery::mock(RedeemPayCode::class);

    $container = new Container;

    $factory = new DefaultClaimExecutionFactory(
        $container,
        $redeemExecutor,
        flowResolverFor(\LBHurtado\XChange\Enums\VoucherFlowType::Disbursable, canDisburse: true),
    );

    expect($factory->make($voucher, []))->toBe($redeemExecutor);
});

it('routes settlement vouchers to settlement executor', function () {
    $voucher = new \LBHurtado\Voucher\Models\Voucher;
    $voucher->forceFill([
        'voucher_type' => 'settlement',
        'code' => 'SETTLE-1234',
    ]);

    $redeemExecutor = Mockery::mock(RedeemPayCode::class);
    $settlementExecutor = Mockery::mock(SettlementExecutionContract::class);

    $container = new Container;
    $container->instance(SettlementExecutionContract::class, $settlementExecutor);

    $factory = new DefaultClaimExecutionFactory(
        $container,
        $redeemExecutor,
        app(\LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract::class),
    );

    $result = $factory->make($voucher, []);

    expect($result)->toBe($settlementExecutor);
});
