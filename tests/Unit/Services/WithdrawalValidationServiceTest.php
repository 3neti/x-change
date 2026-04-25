<?php

declare(strict_types=1);

use LBHurtado\Cash\Contracts\CashWithdrawalAmountBoundsContract;
use LBHurtado\Cash\Contracts\CashWithdrawalValidationContract;
use LBHurtado\Cash\Services\DefaultCashWithdrawalAmountBoundsService;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Adapters\VoucherWithdrawableInstrumentAdapter;
use LBHurtado\XChange\Services\DefaultWithdrawalValidationService;

function withdrawalValidationService(): DefaultWithdrawalValidationService
{
    return new DefaultWithdrawalValidationService(
        validator: app(CashWithdrawalValidationContract::class),
        amountBounds: new DefaultCashWithdrawalAmountBoundsService,
    );
}

function makeVoucher(string $state = 'active'): Voucher
{
    return issueVoucher(validVoucherInstructions(
        amount: 100.00,
        settlementRail: 'INSTAPAY',
    ));
}

it('passes validation for a withdrawable open-slice voucher with valid amount', function () {
    $voucher = Mockery::mock(makeVoucher('active'))->makePartial();

    $voucher->shouldReceive('isDivisible')->andReturnTrue();
    $voucher->shouldReceive('getSliceMode')->andReturn('open');
    $voucher->shouldReceive('isExpired')->andReturnFalse();
    $voucher->shouldReceive('getRemainingBalance')->andReturn(300.00);
    $voucher->shouldReceive('getMinWithdrawal')->andReturn(50.00);
    $voucher->shouldReceive('getMaxSlices')->andReturn(3);
    $voucher->shouldReceive('getConsumedSlices')->andReturn(1);

    $service = withdrawalValidationService();

    $service->validate($voucher, [
        'amount' => 100.00,
    ]);

    expect(true)->toBeTrue();
});

 it('fails validation when voucher is not withdrawable', function () {
    $voucher = Mockery::mock(makeVoucher('active'))->makePartial();

    $voucher->shouldReceive('isDivisible')->andReturnFalse();
    $voucher->shouldReceive('canWithdraw')->once()->andReturnFalse();

    $service = withdrawalValidationService();

    expect(fn () => $service->validate($voucher, [
        'amount' => 100.00,
    ]))->toThrow(RuntimeException::class, 'This voucher is not withdrawable.');
 });

 it('fails validation when open-slice amount exceeds remaining balance', function () {
    $voucher = Mockery::mock(makeVoucher('active'))->makePartial();

    $voucher->shouldReceive('isDivisible')->andReturnTrue();
    $voucher->shouldReceive('getSliceMode')->andReturn('open');
    $voucher->shouldReceive('isExpired')->andReturnFalse();
    $voucher->shouldReceive('getRemainingBalance')->andReturn(100.00);

    $service = withdrawalValidationService();

    expect(fn () => $service->validate($voucher, [
        'amount' => 200.00,
    ]))->toThrow(InvalidArgumentException::class, 'Withdrawal amount exceeds remaining voucher balance.');
 });

 it('fails validation when open-slice amount is below minimum withdrawal amount', function () {
    $voucher = Mockery::mock(makeVoucher('active'))->makePartial();

    $voucher->shouldReceive('isDivisible')->andReturnTrue();
    $voucher->shouldReceive('getSliceMode')->andReturn('open');
    $voucher->shouldReceive('isExpired')->andReturnFalse();
    $voucher->shouldReceive('getRemainingBalance')->andReturn(300.00);
    $voucher->shouldReceive('getMinWithdrawal')->andReturn(50.00);

    $service = withdrawalValidationService();

    expect(fn () => $service->validate($voucher, [
        'amount' => 25.00,
    ]))->toThrow(InvalidArgumentException::class, 'Withdrawal amount must be at least 50.');
 });

 it('fails validation when open-slice amount is missing', function () {
    $voucher = Mockery::mock(makeVoucher('active'))->makePartial();

    $voucher->shouldReceive('isDivisible')->andReturnTrue();
    $voucher->shouldReceive('getSliceMode')->andReturn('open');
    $voucher->shouldReceive('isExpired')->andReturnFalse();

    $service = withdrawalValidationService();

    expect(fn () => $service->validate($voucher, [
        'amount' => null,
    ]))->toThrow(InvalidArgumentException::class, 'Withdrawal amount is required for open-slice vouchers.');
 });

 it('fails validation when open-slice amount is non-numeric', function () {
    $voucher = Mockery::mock(makeVoucher('active'))->makePartial();

    $voucher->shouldReceive('isDivisible')->andReturnTrue();
    $voucher->shouldReceive('getSliceMode')->andReturn('open');
    $voucher->shouldReceive('isExpired')->andReturnFalse();

    $service = withdrawalValidationService();

    expect(fn () => $service->validate($voucher, [
        'amount' => 'abc',
    ]))->toThrow(InvalidArgumentException::class, 'Withdrawal amount must be numeric.');
 });

 it('fails validation when open-slice amount is not greater than zero', function () {
    $voucher = Mockery::mock(makeVoucher('active'))->makePartial();

    $voucher->shouldReceive('isDivisible')->andReturnTrue();
    $voucher->shouldReceive('getSliceMode')->andReturn('open');
    $voucher->shouldReceive('isExpired')->andReturnFalse();

    $service = withdrawalValidationService();

    expect(fn () => $service->validate($voucher, [
        'amount' => 0,
    ]))->toThrow(InvalidArgumentException::class, 'Withdrawal amount must be greater than zero.');
 });

 it('fails validation when open-slice voucher is expired', function () {
    $voucher = Mockery::mock(makeVoucher('active'))->makePartial();

    $voucher->shouldReceive('isDivisible')->andReturnTrue();
    $voucher->shouldReceive('getSliceMode')->andReturn('open');
    $voucher->shouldReceive('isExpired')->andReturnTrue();

    $service = withdrawalValidationService();

    expect(fn () => $service->validate($voucher, [
        'amount' => 100.00,
    ]))->toThrow(RuntimeException::class, 'This voucher has expired.');
 });

 it('fails validation when open-slice voucher has no remaining slices', function () {
    $voucher = Mockery::mock(makeVoucher('active'))->makePartial();

    $voucher->shouldReceive('isDivisible')->andReturnTrue();
    $voucher->shouldReceive('getSliceMode')->andReturn('open');
    $voucher->shouldReceive('isExpired')->andReturnFalse();
    $voucher->shouldReceive('getRemainingBalance')->andReturn(300.00);
    $voucher->shouldReceive('getMinWithdrawal')->andReturn(50.00);
    $voucher->shouldReceive('getMaxSlices')->andReturn(3);
    $voucher->shouldReceive('getConsumedSlices')->andReturn(3);

    $service = withdrawalValidationService();

    expect(fn () => $service->validate($voucher, [
        'amount' => 100.00,
    ]))->toThrow(RuntimeException::class, 'This voucher has no remaining slices.');
 });

it('delegates open-slice amount bounds to cash package', function () {
    $voucher = Mockery::mock(makeVoucher('active'))->makePartial();

    $voucher->shouldReceive('isDivisible')->andReturnTrue();
    $voucher->shouldReceive('getSliceMode')->andReturn('open');
    $voucher->shouldReceive('isExpired')->andReturnFalse();
    $voucher->shouldReceive('getRemainingBalance')->andReturn(100.00);
    $voucher->shouldReceive('getMinWithdrawal')->andReturn(1.00);
    $voucher->shouldReceive('getMaxSlices')->andReturn(3);
    $voucher->shouldReceive('getConsumedSlices')->andReturn(0);

    $bounds = Mockery::mock(CashWithdrawalAmountBoundsContract::class);

    $bounds->shouldReceive('assertWithinBounds')
        ->once()
        ->withArgs(fn ($instrument, $amount, $minimumAmount = null) =>
            $instrument instanceof VoucherWithdrawableInstrumentAdapter
            && $amount === 50
            && $minimumAmount === null
        );

    $service = new DefaultWithdrawalValidationService(
        validator: app(CashWithdrawalValidationContract::class),
        amountBounds: $bounds,
    );

    $service->validate($voucher, [
        'mobile' => '09171234567',
        'amount' => 50,
    ]);
});
