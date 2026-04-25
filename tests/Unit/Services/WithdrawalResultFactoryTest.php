<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Data\PayoutResultData;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\XChange\Services\WithdrawalResultFactory;
use Propaganistas\LaravelPhone\PhoneNumber;

it('builds withdrawal result data from withdrawal context', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 100.00,
        settlementRail: 'INSTAPAY',
    ));

    $contact = Contact::fromPhoneNumber(
        new PhoneNumber('09171234567', 'PH')
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

    $result = app(WithdrawalResultFactory::class)->make(
        voucher: $voucher,
        contact: $contact,
        input: $input,
        response: $response,
        withdrawAmount: 100.00,
        sliceNumber: 1,
    );

    expect($result->voucher_code)->toBe((string) $voucher->code)
        ->and($result->withdrawn)->toBeTrue()
        ->and($result->status)->toBe('withdrawn')
        ->and($result->requested_amount)->toBe(100.00)
        ->and($result->disbursed_amount)->toBe(100.00)
        ->and($result->currency)->toBe('PHP')
        ->and($result->slice_number)->toBe(1)
        ->and($result->redeemer['mobile'])->toBe($contact->mobile)
        ->and($result->bank_account['bank_code'])->toBe('GXCHPHM2XXX')
        ->and($result->bank_account['account_number'])->toBe('09173011987')
        ->and($result->disbursement['status'])->toBe('pending')
        ->and($result->disbursement['transaction_id'])->toBe('TXN-123')
        ->and($result->disbursement['gateway'])->toBe('netbank')
        ->and($result->disbursement['settlement_rail'])->toBe('INSTAPAY')
        ->and($result->messages)->toBe(['Voucher withdrawal successful.']);
});
