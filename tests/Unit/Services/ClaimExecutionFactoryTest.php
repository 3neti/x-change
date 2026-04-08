<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\RedeemPayCode;
use LBHurtado\XChange\Contracts\ClaimExecutorContract;
use LBHurtado\XChange\Services\DefaultClaimExecutionFactory;

it('returns redeem executor for a normal voucher', function () {
    $voucher = Mockery::mock(Voucher::class);
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

it('returns bound withdraw executor when voucher is withdrawable', function () {
    $voucher = Mockery::mock(Voucher::class);
    $voucher->shouldReceive('canWithdraw')->once()->andReturn(true);
    $voucher->shouldReceive('getSliceMode')->never();

    $redeemExecutor = Mockery::mock(RedeemPayCode::class);
    $withdrawExecutor = Mockery::mock(ClaimExecutorContract::class);

    $container = new Container;
    $container->instance('xchange.withdraw.executor', $withdrawExecutor);

    $factory = new DefaultClaimExecutionFactory(
        $container,
        $redeemExecutor,
    );

    $result = $factory->make($voucher, []);

    expect($result)->toBe($withdrawExecutor);
});

it('returns bound withdraw executor when voucher has a slice mode', function () {
    $voucher = Mockery::mock(Voucher::class);
    $voucher->shouldReceive('canWithdraw')->once()->andReturn(false);
    $voucher->shouldReceive('getSliceMode')->once()->andReturn('open');

    $redeemExecutor = Mockery::mock(RedeemPayCode::class);
    $withdrawExecutor = Mockery::mock(ClaimExecutorContract::class);

    $container = new Container;
    $container->instance('xchange.withdraw.executor', $withdrawExecutor);

    $factory = new DefaultClaimExecutionFactory(
        $container,
        $redeemExecutor,
    );

    $result = $factory->make($voucher, []);

    expect($result)->toBe($withdrawExecutor);
});

it('falls back to redeem executor when voucher should withdraw but no withdraw executor is bound', function () {
    $voucher = Mockery::mock(Voucher::class);
    $voucher->shouldReceive('canWithdraw')->once()->andReturn(true);
    $voucher->shouldReceive('getSliceMode')->never();

    $redeemExecutor = Mockery::mock(RedeemPayCode::class);

    $container = new Container;

    $factory = new DefaultClaimExecutionFactory(
        $container,
        $redeemExecutor,
    );

    $result = $factory->make($voucher, []);

    expect($result)->toBe($redeemExecutor);
});
