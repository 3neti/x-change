<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use LBHurtado\Contact\Classes\BankAccount;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Data\PayoutResultData;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\XChange\Data\Redemption\WithdrawPayCodeResultData;
use LBHurtado\XChange\Data\WithdrawalDisbursementExecutionData;
use LBHurtado\XChange\Data\WithdrawalPipelineContextData;
use LBHurtado\XChange\Data\WithdrawalWalletSettlementData;
use LBHurtado\XChange\Services\WithdrawalPipelineSteps\BuildWithdrawalResultStep;
use LBHurtado\XChange\Services\WithdrawalResultFactory;
use Propaganistas\LaravelPhone\PhoneNumber;

it('builds withdrawal result and stores it on context', function () {
    $voucher = issueVoucher();

    $contact = Contact::fromPhoneNumber(
        new PhoneNumber('09171234567', 'PH'),
    );

    $input = PayoutRequestData::from([
        'reference' => $voucher->code.'-09173011987-S1',
        'amount' => 100.00,
        'account_number' => '09173011987',
        'bank_code' => 'GXCHPHM2XXX',
        'settlement_rail' => 'INSTAPAY',
    ]);

    $response = PayoutResultData::from([
        'uuid' => (string) Str::uuid(),
        'transaction_id' => 'TXN-123',
        'status' => PayoutStatus::PENDING,
        'provider' => 'netbank',
        'raw' => [],
    ]);

    $disbursement = new WithdrawalDisbursementExecutionData(
        input: $input,
        response: $response,
        status: 'pending',
        message: null,
    );

    $settlement = new WithdrawalWalletSettlementData(
        transfer: (object) ['id' => 123],
        feeAmount: 0.0,
        feeStrategy: 'absorb',
    );

    $expected = new WithdrawPayCodeResultData(
        voucher_code: (string) $voucher->code,
        withdrawn: true,
        status: 'withdrawn',
        requested_amount: 100.00,
        disbursed_amount: 100.00,
        currency: 'PHP',
        remaining_balance: 0.0,
        slice_number: 1,
        remaining_slices: 0,
        slice_mode: null,
        redeemer: [],
        bank_account: [],
        disbursement: [],
        messages: ['Voucher withdrawal successful.'],
    );

    $factory = Mockery::mock(WithdrawalResultFactory::class);
    $factory->shouldReceive('make')
        ->once()
        ->withArgs(fn (...$args) => true)
        ->andReturn($expected);

    $step = new BuildWithdrawalResultStep($factory);

    $context = new WithdrawalPipelineContextData(
        voucher: $voucher,
        payload: [],
        contact: $contact,
        withdrawAmount: 100.00,
        bankAccount: BankAccount::fromBankAccount('GXCHPHM2XXX:09173011987'),
        payoutRequest: $input,
        disbursement: $disbursement,
        sliceNumber: 1,
        settlement: $settlement,
    );

    $result = $step->handle($context, fn ($ctx) => $ctx);

    expect($result->result)->toBe($expected);
});

it('fails when settlement is missing before result construction', function () {
    $voucher = issueVoucher();

    $step = new BuildWithdrawalResultStep(
        Mockery::mock(WithdrawalResultFactory::class),
    );

    $context = new WithdrawalPipelineContextData(
        voucher: $voucher,
        payload: [],
        contact: fakePayoutContact(),
        withdrawAmount: 100.00,
        payoutRequest: PayoutRequestData::from([
            'reference' => $voucher->code.'-09173011987-S1',
            'amount' => 100.00,
            'account_number' => '09173011987',
            'bank_code' => 'GXCHPHM2XXX',
            'settlement_rail' => 'INSTAPAY',
        ]),
        disbursement: fakeWithdrawalDisbursementExecution(),
        sliceNumber: 1,
    );

    $step->handle($context, fn ($ctx) => $ctx);
})->throws(LogicException::class, 'Withdrawal wallet settlement must be completed before result construction.');
