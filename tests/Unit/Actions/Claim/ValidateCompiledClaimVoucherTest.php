<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Enums\VoucherSliceSelectionPolicy;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Services\VoucherSlicePlanFactory;
use LBHurtado\Wallet\Actions\WithdrawCash;
use LBHurtado\XChange\Actions\Claim\ValidateCompiledClaimVoucher;
use LBHurtado\XChange\Models\VoucherSliceExecution;
use LBHurtado\XChange\Models\VoucherSliceExecutionItem;
use LBHurtado\XChange\Services\Slices\VoucherSliceExecutionCoordinator;

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
    $plan = app(VoucherSlicePlanFactory::class)->scheduled(
        totalMinor: 15_500,
        currency: 'PHP',
        slices: [
            ['id' => 'slice_1', 'label' => 'Buy coffee', 'amount_minor' => 8_000],
            ['id' => 'slice_2', 'label' => 'Buy doughnut', 'amount_minor' => 7_500],
        ],
        selection: VoucherSliceSelectionPolicy::OneOrMany,
    );
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 155,
        overrides: [
            'cash' => [
                'amount' => 155,
                'currency' => 'PHP',
                'validation' => [
                    'country' => 'PH',
                ],
            ],
            'slice_plan' => $plan->canonicalArray(),
        ],
    ));

    $voucher->forceFill([
        'redeemed_at' => now(),
        'state' => VoucherState::ACTIVE,
        'expires_at' => Carbon::now()->addDay(),
    ])->save();

    $reference = (string) Str::ulid();
    $execution = VoucherSliceExecution::query()->create([
        'reference' => $reference,
        'voucher_id' => $voucher->getKey(),
        'plan_fingerprint' => app(VoucherSliceExecutionCoordinator::class)->plan($voucher)->hash(),
        'idempotency_key_hash' => hash('sha256', $reference),
        'request_fingerprint' => hash('sha256', 'request-'.$reference),
        'provider_operation_reference' => 'slice-'.$reference,
        'claim_number' => 1,
        'status' => 'succeeded',
        'amount_minor' => 8_000,
        'currency' => 'PHP',
        'reserved_at' => now(),
        'settled_at' => now(),
    ]);
    VoucherSliceExecutionItem::query()->create([
        'execution_id' => $execution->getKey(),
        'voucher_id' => $voucher->getKey(),
        'slice_id' => 'slice_1',
        'label' => 'Buy coffee',
        'sequence' => 1,
        'amount_minor' => 8_000,
        'status' => 'consumed',
        'reserved_at' => now(),
        'consumed_at' => now(),
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
