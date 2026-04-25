<?php

declare(strict_types=1);

use LBHurtado\Contact\Classes\BankAccount;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;
use LBHurtado\XChange\Data\WithdrawalWalletSettlementData;
use LBHurtado\XChange\Services\WithdrawalPipelineSteps\WithdrawalWalletSettlementStep;
use LBHurtado\XChange\Services\WithdrawalWalletSettlementService;

it('settles wallet withdrawal and stores settlement on context', function () {
    $voucher = issueVoucher();

    $input = PayoutRequestData::from([
        'reference' => $voucher->code.'-09173011987-S1',
        'amount' => 100.00,
        'account_number' => '09173011987',
        'bank_code' => 'GXCHPHM2XXX',
        'settlement_rail' => 'INSTAPAY',
    ]);

    $settlement = new WithdrawalWalletSettlementData(
        transfer: (object) ['id' => 123],
        feeAmount: 0.0,
        feeStrategy: 'absorb',
    );

    $service = Mockery::mock(WithdrawalWalletSettlementService::class);
    $service->shouldReceive('settle')
        ->once()
        ->withArgs(fn ($v, $i, $amount, $slice) => $v->is($voucher)
            && $i === $input
            && $amount === 100.00
            && $slice === 1
        )
        ->andReturn($settlement);

    $step = new WithdrawalWalletSettlementStep($service);

    $context = new WithdrawalPipelineContextData(
        voucher: $voucher,
        payload: [],
        withdrawAmount: 100.00,
        bankAccount: BankAccount::fromBankAccount('GXCHPHM2XXX:09173011987'),
        payoutRequest: $input,
        sliceNumber: 1,
    );

    $result = $step->handle($context, fn ($ctx) => $ctx);

    expect($result->settlement)->toBe($settlement);
});

it('fails when payout request is missing before wallet settlement', function () {
    $step = new WithdrawalWalletSettlementStep(
        Mockery::mock(WithdrawalWalletSettlementService::class),
    );

    $context = new WithdrawalPipelineContextData(
        voucher: issueVoucher(),
        payload: [],
        withdrawAmount: 100.00,
        sliceNumber: 1,
    );

    $step->handle($context, fn ($ctx) => $ctx);
})->throws(LogicException::class, 'Withdrawal payout request must be built before wallet settlement.');
