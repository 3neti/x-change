<?php

use Illuminate\Support\Carbon;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\XChange\Actions\Claim\ValidateCompiledClaimVoucher;

it('rejects missing voucher', function () {
    expect(app(ValidateCompiledClaimVoucher::class)->handle(null))
        ->toBe('Invalid Pay Code.');
});

it('rejects redeemed voucher', function () {
    $voucher = issueVoucher();

    $voucher->update([
        'redeemed_at' => now(),
    ]);

    expect(app(ValidateCompiledClaimVoucher::class)->handle($voucher->fresh()))
        ->toBe('This Pay Code has already been redeemed.');
});

it('rejects expired voucher', function () {
    $voucher = issueVoucher();

    $voucher->update([
        'expires_at' => Carbon::now()->subMinute(),
    ]);

    expect(app(ValidateCompiledClaimVoucher::class)->handle($voucher->fresh()))
        ->toBe('This Pay Code has expired.');
});

it('rejects non-redeemable voucher lifecycle state', function () {
    $voucher = issueVoucher();

    $voucher->update([
        'state' => VoucherState::LOCKED,
        'redeemed_at' => null,
        'expires_at' => Carbon::now()->addDay(),
    ]);

    expect(app(ValidateCompiledClaimVoucher::class)->handle($voucher->fresh()))
        ->toBe('This Pay Code cannot be redeemed.');
});

it('accepts redeemable voucher', function () {
    $voucher = issueVoucher();

    $voucher->update([
        'state' => VoucherState::ACTIVE,
        'redeemed_at' => null,
        'expires_at' => Carbon::now()->addDay(),
    ]);

    expect(app(ValidateCompiledClaimVoucher::class)->handle($voucher->fresh()))
        ->toBeNull();
});
