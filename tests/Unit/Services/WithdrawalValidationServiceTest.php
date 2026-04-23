<?php

declare(strict_types=1);

use LBHurtado\Cash\Services\DefaultCashWithdrawalValidationService;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Services\DefaultWithdrawalValidationService;
use LBHurtado\XChange\Services\XChangeWithdrawalIntervalEnforcer;

function makeVoucher(string $state = 'active'): Voucher
{
    $voucher = new Voucher;
    $voucher->forceFill([
        'code' => 'TEST-VAL-001',
        'state' => $state,
        'metadata' => [],
    ]);

    return $voucher;
}

it('passes validation for a withdrawable open-slice voucher with valid amount', function () {
    $voucher = Mockery::mock(makeVoucher('active'))->makePartial();

    $voucher->shouldReceive('isDivisible')->once()->andReturnTrue();
    $voucher->shouldReceive('getSliceMode')->once()->andReturn('open');
    $voucher->shouldReceive('isExpired')->once()->andReturnFalse();
    $voucher->shouldReceive('getRemainingBalance')->once()->andReturn(300.00);
    $voucher->shouldReceive('getMinWithdrawal')->once()->andReturn(50.00);
    $voucher->shouldReceive('getMaxSlices')->once()->andReturn(3);
    $voucher->shouldReceive('getConsumedSlices')->once()->andReturn(1);

    $service = new DefaultWithdrawalValidationService(
        new DefaultCashWithdrawalValidationService(
            new XChangeWithdrawalIntervalEnforcer,
        ),
    );

    $service->validate($voucher, [
        'amount' => 100.00,
    ]);

    expect(true)->toBeTrue();
});

it('fails validation when voucher is not withdrawable', function () {
    $voucher = Mockery::mock(makeVoucher('active'))->makePartial();

    $voucher->shouldReceive('isDivisible')->once()->andReturnFalse();
    $voucher->shouldReceive('canWithdraw')->once()->andReturnFalse();

    $service = new DefaultWithdrawalValidationService(
        new DefaultCashWithdrawalValidationService(
            new XChangeWithdrawalIntervalEnforcer,
        ),
    );

    expect(fn () => $service->validate($voucher, [
        'amount' => 100.00,
    ]))->toThrow(RuntimeException::class, 'This voucher is not withdrawable.');
});

it('fails validation when open-slice amount exceeds remaining balance', function () {
    $voucher = Mockery::mock(makeVoucher('active'))->makePartial();

    $voucher->shouldReceive('isDivisible')->once()->andReturnTrue();
    $voucher->shouldReceive('getSliceMode')->once()->andReturn('open');
    $voucher->shouldReceive('isExpired')->once()->andReturnFalse();
    $voucher->shouldReceive('getRemainingBalance')->once()->andReturn(100.00);

    $service = new DefaultWithdrawalValidationService(
        new DefaultCashWithdrawalValidationService(
            new XChangeWithdrawalIntervalEnforcer,
        ),
    );

    expect(fn () => $service->validate($voucher, [
        'amount' => 200.00,
    ]))->toThrow(InvalidArgumentException::class, 'Withdrawal amount exceeds remaining balance.');
});

it('fails validation when open-slice amount is below minimum withdrawal amount', function () {
    $voucher = Mockery::mock(makeVoucher('active'))->makePartial();

    $voucher->shouldReceive('isDivisible')->once()->andReturnTrue();
    $voucher->shouldReceive('getSliceMode')->once()->andReturn('open');
    $voucher->shouldReceive('isExpired')->once()->andReturnFalse();
    $voucher->shouldReceive('getRemainingBalance')->once()->andReturn(300.00);
    $voucher->shouldReceive('getMinWithdrawal')->once()->andReturn(50.00);

    $service = new DefaultWithdrawalValidationService(
        new DefaultCashWithdrawalValidationService(
            new XChangeWithdrawalIntervalEnforcer,
        ),
    );

    expect(fn () => $service->validate($voucher, [
        'amount' => 25.00,
    ]))->toThrow(InvalidArgumentException::class, 'Withdrawal amount is below the minimum withdrawal amount.');
});

it('fails validation when open-slice amount is missing', function () {
    $voucher = Mockery::mock(makeVoucher('active'))->makePartial();

    $voucher->shouldReceive('isDivisible')->once()->andReturnTrue();
    $voucher->shouldReceive('getSliceMode')->once()->andReturn('open');
    $voucher->shouldReceive('isExpired')->once()->andReturnFalse();

    $service = new DefaultWithdrawalValidationService(
        new DefaultCashWithdrawalValidationService(
            new XChangeWithdrawalIntervalEnforcer,
        ),
    );

    expect(fn () => $service->validate($voucher, [
        'amount' => null,
    ]))->toThrow(InvalidArgumentException::class, 'Withdrawal amount is required.');
});

it('fails validation when open-slice amount is non-numeric', function () {
    $voucher = Mockery::mock(makeVoucher('active'))->makePartial();

    $voucher->shouldReceive('isDivisible')->once()->andReturnTrue();
    $voucher->shouldReceive('getSliceMode')->once()->andReturn('open');
    $voucher->shouldReceive('isExpired')->once()->andReturnFalse();

    $service = new DefaultWithdrawalValidationService(
        new DefaultCashWithdrawalValidationService(
            new XChangeWithdrawalIntervalEnforcer,
        ),
    );

    expect(fn () => $service->validate($voucher, [
        'amount' => 'abc',
    ]))->toThrow(InvalidArgumentException::class, 'Withdrawal amount must be numeric.');
});

it('fails validation when open-slice amount is not greater than zero', function () {
    $voucher = Mockery::mock(makeVoucher('active'))->makePartial();

    $voucher->shouldReceive('isDivisible')->once()->andReturnTrue();
    $voucher->shouldReceive('getSliceMode')->once()->andReturn('open');
    $voucher->shouldReceive('isExpired')->once()->andReturnFalse();

    $service = new DefaultWithdrawalValidationService(
        new DefaultCashWithdrawalValidationService(
            new XChangeWithdrawalIntervalEnforcer,
        ),
    );

    expect(fn () => $service->validate($voucher, [
        'amount' => 0,
    ]))->toThrow(InvalidArgumentException::class, 'Withdrawal amount must be greater than zero.');
});

it('fails validation when open-slice voucher is expired', function () {
    $voucher = Mockery::mock(makeVoucher('active'))->makePartial();

    $voucher->shouldReceive('isDivisible')->once()->andReturnTrue();
    $voucher->shouldReceive('getSliceMode')->once()->andReturn('open');
    $voucher->shouldReceive('isExpired')->once()->andReturnTrue();

    $service = new DefaultWithdrawalValidationService(
        new DefaultCashWithdrawalValidationService(
            new XChangeWithdrawalIntervalEnforcer,
        ),
    );

    expect(fn () => $service->validate($voucher, [
        'amount' => 100.00,
    ]))->toThrow(RuntimeException::class, 'This voucher has expired.');
});

it('fails validation when open-slice voucher has no remaining slices', function () {
    $voucher = Mockery::mock(makeVoucher('active'))->makePartial();

    $voucher->shouldReceive('isDivisible')->once()->andReturnTrue();
    $voucher->shouldReceive('getSliceMode')->once()->andReturn('open');
    $voucher->shouldReceive('isExpired')->once()->andReturnFalse();
    $voucher->shouldReceive('getRemainingBalance')->once()->andReturn(300.00);
    $voucher->shouldReceive('getMinWithdrawal')->once()->andReturn(50.00);
    $voucher->shouldReceive('getMaxSlices')->once()->andReturn(3);
    $voucher->shouldReceive('getConsumedSlices')->once()->andReturn(3);

    $service = new DefaultWithdrawalValidationService(
        new DefaultCashWithdrawalValidationService(
            new XChangeWithdrawalIntervalEnforcer,
        ),
    );

    expect(fn () => $service->validate($voucher, [
        'amount' => 100.00,
    ]))->toThrow(RuntimeException::class, 'This voucher has no remaining slices.');
});
