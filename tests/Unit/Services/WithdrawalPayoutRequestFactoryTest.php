<?php

declare(strict_types=1);

use LBHurtado\Contact\Classes\BankAccount;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\XChange\Services\WithdrawalPayoutRequestFactory;
use Spatie\SchemalessAttributes\SchemalessAttributes;

// TODO: Strengthen this once PayoutRequestData exposes recipient identity fields
// or recipient normalization is extracted into a dedicated service.
function payoutRequestFactory(): WithdrawalPayoutRequestFactory
{
    return new WithdrawalPayoutRequestFactory;
}

it('builds a payout request from withdrawal context', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 750.00,
        settlementRail: 'INSTAPAY',
    ));
    $request = payoutRequestFactory()->make(
        $voucher,
        fakePayoutContact(),
        BankAccount::fromBankAccount('GXCHPHM2XXX:09173011987'),
        $voucher->code.'-09173011987-S1',
        750.00,
    );

    expect($request->reference)->toBe($voucher->code.'-09173011987-S1')
        ->and($request->amount)->toBe(750.00)
        ->and($request->account_number)->toBe('09173011987')
        ->and($request->bank_code)->toBe('GXCHPHM2XXX')
        ->and($request->settlement_rail)->toBe('INSTAPAY');
});

it('uses configured settlement rail from voucher instructions', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 750.00,
        settlementRail: 'PESONET',
    ));
    $request = payoutRequestFactory()->make(
        $voucher,
        fakePayoutContact(),
        BankAccount::fromBankAccount('GXCHPHM2XXX:09173011987'),
        $voucher->code.'-09173011987-S1',
        750.00,
    );

    expect($request->settlement_rail)->toBe('PESONET');
});

it('falls back to pesonet for large amounts', function () {
    actingAsTestUser(10_000_000);
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 50000.00,
        settlementRail: null,
    ));
    $request = payoutRequestFactory()->make(
        $voucher,
        fakePayoutContact(),
        BankAccount::fromBankAccount('GXCHPHM2XXX:09173011987'),
        $voucher->code.'-09173011987-S1',
        50000.00,
    );

    expect($request->settlement_rail)->toBe('PESONET');
});

it('allows contact name to be missing when mobile is present', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 750.00,
        settlementRail: 'INSTAPAY',
    ));
    $request = payoutRequestFactory()->make(
        $voucher,
        fakePayoutContact(name: null, mobile: '09171234567'),
        BankAccount::fromBankAccount('GXCHPHM2XXX:09173011987'),
        $voucher->code.'-09173011987-S1',
        750.00,
    );

    expect($request->reference)->toBe($voucher->code.'-09173011987-S1');
});
