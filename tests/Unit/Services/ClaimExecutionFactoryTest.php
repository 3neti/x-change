<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use LBHurtado\XChange\Actions\Redemption\RedeemPayCode;
use LBHurtado\XChange\Actions\Redemption\WithdrawPayCode;
use LBHurtado\XChange\Services\DefaultClaimExecutionFactory;

it('returns redeem executor for a normal voucher', function () {
    $voucher = Mockery::mock(\LBHurtado\Voucher\Models\Voucher::class);
    $voucher->shouldReceive('canWithdraw')->once()->andReturn(false);
    $voucher->shouldReceive('getSliceMode')->once()->andReturn(null);

    $redeemExecutor = Mockery::mock(RedeemPayCode::class);

    $container = new Container;

    $factory = new DefaultClaimExecutionFactory(
        $container,
        $redeemExecutor,
    );

    $result = $factory->make($voucher, []);

    expect($result)->toBe($redeemExecutor);
});

it('returns withdraw executor when voucher is withdrawable', function () {
    $voucher = Mockery::mock(\LBHurtado\Voucher\Models\Voucher::class);
    $voucher->shouldReceive('canWithdraw')->once()->andReturn(true);
    $voucher->shouldReceive('getSliceMode')->never();

    $redeemExecutor = Mockery::mock(RedeemPayCode::class);
    $withdrawExecutor = Mockery::mock(WithdrawPayCode::class);

    $container = new Container;
    $container->instance(WithdrawPayCode::class, $withdrawExecutor);

    $factory = new DefaultClaimExecutionFactory(
        $container,
        $redeemExecutor,
    );

    $result = $factory->make($voucher, []);

    expect($result)->toBe($withdrawExecutor);
});

it('returns withdraw executor when voucher has a slice mode', function () {
    $voucher = Mockery::mock(\LBHurtado\Voucher\Models\Voucher::class);
    $voucher->shouldReceive('canWithdraw')->once()->andReturn(false);
    $voucher->shouldReceive('getSliceMode')->once()->andReturn('open');

    $redeemExecutor = Mockery::mock(RedeemPayCode::class);
    $withdrawExecutor = Mockery::mock(WithdrawPayCode::class);

    $container = new Container;
    $container->instance(WithdrawPayCode::class, $withdrawExecutor);

    $factory = new DefaultClaimExecutionFactory(
        $container,
        $redeemExecutor,
    );

    $result = $factory->make($voucher, []);

    expect($result)->toBe($withdrawExecutor);
});
