<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\RedeemPayCode;
use LBHurtado\XChange\Actions\Redemption\WithdrawPayCode;
use LBHurtado\XChange\Contracts\ClaimExecutionFactoryContract;
use LBHurtado\XChange\Contracts\SettlementExecutionContract;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Data\VoucherFlow\VoucherFlowCapabilitiesData;
use LBHurtado\XChange\Enums\VoucherFlowType;
use LBHurtado\XChange\Exceptions\VoucherCannotDisburse;
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

function flowResolverFor(VoucherFlowType $type, bool $canDisburse): VoucherFlowCapabilityResolverContract
{
    $resolver = Mockery::mock(VoucherFlowCapabilityResolverContract::class);

    $resolver->shouldReceive('resolve')
        ->byDefault()
        ->andReturn(new VoucherFlowCapabilitiesData(
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
    $voucher = Mockery::mock(Voucher::class);
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
    $voucher = Mockery::mock(Voucher::class);
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
    $voucher = Mockery::mock(Voucher::class);
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
    $voucher = new Voucher;
    $voucher->setAttribute('metadata', [
        'flow_type' => 'collectible',
    ]);

    $factory = app(ClaimExecutionFactoryContract::class);

    expect(fn () => $factory->make($voucher, []))
        ->toThrow(RuntimeException::class, 'cannot execute outward claims');
});

it('routes open-slice disbursable vouchers to withdrawal executor', function () {
    $voucher = Mockery::mock(Voucher::class);

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

it('rejects legacy payable vouchers for outward claim execution', function () {
    $voucher = new Voucher;
    $voucher->setAttribute('metadata', [
        'voucher_type' => 'payable',
    ]);

    $factory = app(ClaimExecutionFactoryContract::class);

    expect(fn () => $factory->make($voucher, []))
        ->toThrow(VoucherCannotDisburse::class);
});

it('allows legacy redeemable vouchers for outward claim execution', function () {
    $voucher = Mockery::mock(Voucher::class);

    $voucher->shouldReceive('isDivisible')->once()->andReturn(false);
    $voucher->shouldReceive('isRedeemed')->once()->andReturn(false);
    $voucher->shouldReceive('canWithdraw')->never();
    $voucher->shouldReceive('getSliceMode')->never();

    $redeemExecutor = Mockery::mock(RedeemPayCode::class);

    $container = new Container;

    $factory = new DefaultClaimExecutionFactory(
        $container,
        $redeemExecutor,
        flowResolverFor(VoucherFlowType::Disbursable, canDisburse: true),
    );

    expect($factory->make($voucher, []))->toBe($redeemExecutor);
});

it('routes settlement vouchers to settlement executor', function () {
    $voucher = new Voucher;
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
        app(VoucherFlowCapabilityResolverContract::class),
    );

    $result = $factory->make($voucher, []);

    expect($result)->toBe($settlementExecutor);
});
