<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\Claim\ClaimShareCardAmountFormatter;

it('uses the peso symbol for a PHP claim share card', function (): void {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'cash' => [
            'amount' => 57,
            'currency' => 'PHP',
        ],
    ]));

    expect(app(ClaimShareCardAmountFormatter::class)->format($voucher))
        ->toBe('₱57.00');
});

it('keeps the currency code for non-PHP claim share cards', function (): void {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'cash' => [
            'amount' => 57,
            'currency' => 'USD',
        ],
    ]));

    expect(app(ClaimShareCardAmountFormatter::class)->format($voucher))
        ->toBe('USD 57.00');
});

it('shows the collection target for a payable stamp', function (): void {
    $user = actingAsTestUser();
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'cash' => ['amount' => 0, 'currency' => 'PHP'],
        'voucher_type' => 'payable', 'target_amount' => 100,
        'metadata' => ['collection_wallet_id' => (string) $user->wallet->id],
    ]));
    expect(app(ClaimShareCardAmountFormatter::class)->format($voucher))->toBe('₱100.00');
});
