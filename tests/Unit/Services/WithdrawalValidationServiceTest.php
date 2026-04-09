<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Services\DefaultWithdrawalValidationService;

it('passes validation for a withdrawable open-slice voucher with valid amount', function () {
    $voucher = Mockery::mock(Voucher::class);
    $voucher->shouldReceive('canWithdraw')->once()->andReturn(true);
    $voucher->shouldReceive('getSliceMode')->once()->andReturn('open');
    $voucher->shouldReceive('getRemainingBalance')->once()->andReturn(500.00);
    $voucher->shouldReceive('getMinWithdrawal')->once()->andReturn(100.00);

    $service = new DefaultWithdrawalValidationService;

    expect(fn () => $service->validate($voucher, [
        'amount' => 200.00,
    ]))->not->toThrow(Throwable::class);
});

it('fails validation when voucher is not withdrawable', function () {
    $voucher = Mockery::mock(Voucher::class);
    $voucher->shouldReceive('canWithdraw')->once()->andReturn(false);
    $voucher->shouldReceive('getSliceMode')->never();

    $service = new DefaultWithdrawalValidationService;

    expect(fn () => $service->validate($voucher, [
        'amount' => 100.00,
    ]))->toThrow(RuntimeException::class, 'This voucher is not withdrawable.');
});

it('fails validation when open-slice amount exceeds remaining balance', function () {
    $voucher = Mockery::mock(Voucher::class);
    $voucher->shouldReceive('canWithdraw')->once()->andReturn(true);
    $voucher->shouldReceive('getSliceMode')->once()->andReturn('open');
    $voucher->shouldReceive('getRemainingBalance')->once()->andReturn(100.00);

    $service = new DefaultWithdrawalValidationService;

    expect(fn () => $service->validate($voucher, [
        'amount' => 200.00,
    ]))->toThrow(InvalidArgumentException::class, 'Withdrawal amount exceeds remaining balance.');
});
