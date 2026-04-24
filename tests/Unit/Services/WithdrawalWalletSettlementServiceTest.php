<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\XChange\Services\WithdrawalWalletSettlementService;

it('settles wallet withdrawal for a voucher', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 100.00,
        settlementRail: 'INSTAPAY',
    ));

    $input = PayoutRequestData::from([
        'reference' => $voucher->code.'-09173011987-S1',
        'amount' => 100.00,
        'account_number' => '09173011987',
        'bank_code' => 'GXCHPHM2XXX',
        'settlement_rail' => 'INSTAPAY',
    ]);

    $settlement = app(WithdrawalWalletSettlementService::class)->settle(
        voucher: $voucher,
        input: $input,
        withdrawAmount: 100.00,
        sliceNumber: 1,
    );

    expect($settlement->transfer)->not->toBeNull()
        ->and($settlement->feeAmount)->toBe(0.0)
        ->and($settlement->feeStrategy)->toBe('absorb');
});
