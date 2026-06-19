<?php

use Illuminate\Support\Carbon;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Wallet\Actions\WithdrawCash;
use LBHurtado\XChange\Actions\Claim\ValidateCompiledClaimVoucher;
use LBHurtado\XChange\Models\VoucherClaim;

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

it('accepts redeemed named slice voucher while slices remain unclaimed', function () {
    $voucher = namedSliceVoucher([
        [
            'id' => 'slice_1',
            'amount' => 80,
            'description' => 'Buy coffee',
        ],
        [
            'id' => 'slice_2',
            'amount' => 75,
            'description' => 'Buy doughnut',
        ],
    ]);

    $voucher->forceFill([
        'redeemed_at' => now(),
        'state' => VoucherState::ACTIVE,
        'expires_at' => Carbon::now()->addDay(),
    ])->save();

    VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => 1,
        'claim_type' => 'withdraw',
        'status' => 'withdrawn',
        'currency' => 'PHP',
        'attempted_at' => now(),
        'meta' => [
            'named_slices' => [
                'selected_ids' => ['slice_1'],
            ],
        ],
    ]);

    expect(app(ValidateCompiledClaimVoucher::class)->handle($voucher->fresh()))
        ->toBeNull();
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

it('rejects exhausted divisible vouchers as already redeemed', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 25,
        overrides: [
            'cash' => [
                'slice_mode' => 'open',
                'max_slices' => 1,
                'min_withdrawal' => 25,
            ],
        ],
    ));

    WithdrawCash::run($voucher->cash, 'TEST-EXHAUSTED-S1', 'Exhaust voucher', [
        'flow' => 'withdraw',
    ], 2500);

    expect(app(ValidateCompiledClaimVoucher::class)->handle($voucher->fresh()))
        ->toBe('This Pay Code has already been redeemed.');
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
