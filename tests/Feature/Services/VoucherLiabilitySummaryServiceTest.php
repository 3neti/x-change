<?php

declare(strict_types=1);

use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherLiabilitySummaryContract;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Tests\Fakes\User;

function issueLiabilityVoucher(User $issuer, float $amount, array $overrides = []): Voucher
{
    test()->actingAs($issuer);

    return GenerateVouchers::run(validVoucherInstructions($amount, overrides: $overrides))->first();
}

it('summarizes active redeemed expired and cancelled voucher liability without mutating wallets', function () {
    $issuer = actingAsTestUser();

    issueLiabilityVoucher($issuer, 25);

    $redeemed = issueLiabilityVoucher($issuer, 30);
    $redeemed->forceFill(['redeemed_at' => now()])->save();

    $expired = issueLiabilityVoucher($issuer, 40);
    $expired->forceFill(['expires_at' => now()->subDay()])->save();

    $cancelled = issueLiabilityVoucher($issuer, 50);
    $cancelled->forceFill([
        'state' => VoucherState::CLOSED,
        'closed_at' => now(),
    ])->save();

    $summary = app(VoucherLiabilitySummaryContract::class)
        ->forIssuer($issuer);

    expect($summary->read_only)->toBeTrue()
        ->and($summary->wallet_balance_minor)->toBe(985_500)
        ->and($summary->active_issued_minor)->toBe(2_500)
        ->and($summary->redeemed_minor)->toBe(3_000)
        ->and($summary->expired_minor)->toBe(4_000)
        ->and($summary->cancelled_minor)->toBe(5_000)
        ->and($summary->outstanding_liability_minor)->toBe(2_500)
        ->and($summary->usable_balance_estimate_minor)->toBe(983_000)
        ->and($summary->active_count)->toBe(1)
        ->and($summary->redeemed_count)->toBe(1)
        ->and($summary->expired_count)->toBe(1)
        ->and($summary->cancelled_count)->toBe(1)
        ->and($summary->redactions['mutates_wallets'])->toBeFalse()
        ->and($summary->redactions['releases_funds'])->toBeFalse();
});

it('uses latest remaining claim balance for partial outstanding liability', function () {
    $issuer = actingAsTestUser();
    $voucher = issueLiabilityVoucher($issuer, 100);

    VoucherClaim::query()->create([
        'voucher_id' => $voucher->id,
        'claim_number' => 1,
        'claim_type' => 'withdraw',
        'status' => 'succeeded',
        'requested_amount_minor' => 25_000,
        'disbursed_amount_minor' => 25_000,
        'remaining_balance_minor' => 75_000,
        'currency' => 'PHP',
    ]);

    VoucherClaim::query()->create([
        'voucher_id' => $voucher->id,
        'claim_number' => 2,
        'claim_type' => 'withdraw',
        'status' => 'succeeded',
        'requested_amount_minor' => 25_000,
        'disbursed_amount_minor' => 25_000,
        'remaining_balance_minor' => 50_000,
        'currency' => 'PHP',
    ]);

    $summary = app(VoucherLiabilitySummaryContract::class)
        ->forIssuer($issuer);

    expect($summary->active_issued_minor)->toBe(50_000)
        ->and($summary->outstanding_liability_minor)->toBe(50_000)
        ->and($summary->usable_balance_estimate_minor)->toBe(940_000);
});
