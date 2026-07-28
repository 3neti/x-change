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
