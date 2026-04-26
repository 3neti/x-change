<?php

declare(strict_types=1);

it('persists cash vendor mandates on issued voucher instructions', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 1000.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'cash' => [
                'type' => 'withdrawable',
                'mandates' => [
                    [
                        'alias' => 'MERALCO',
                        'max_amount' => 1000.00,
                    ],
                ],
            ],
        ],
    ));

    expect($voucher->instructions->cash->type)->toBe('withdrawable')
        ->and($voucher->instructions->cash->mandates[0]['alias'])->toBe('MERALCO')
        ->and((float) $voucher->instructions->cash->mandates[0]['max_amount'])->toBe(1000.00);
});
