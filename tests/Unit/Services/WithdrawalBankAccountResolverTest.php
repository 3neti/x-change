<?php

declare(strict_types=1);

use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Services\WithdrawalBankAccountResolver;

function bankAccountResolver(): WithdrawalBankAccountResolver
{
    return new WithdrawalBankAccountResolver;
}

function fakeBankAccountContact(?string $bankAccount = null): Contact
{
    $contact = Mockery::mock(Contact::class)->makePartial();
    $contact->bank_account = $bankAccount;

    return $contact;
}

it('resolves bank account from nested bank_account payload', function () {
    $bankAccount = bankAccountResolver()->resolve(
        Mockery::mock(Voucher::class),
        fakeBankAccountContact(),
        [
            'bank_account' => [
                'bank_code' => 'GXCHPHM2XXX',
                'account_number' => '09173011987',
            ],
        ],
    );

    expect($bankAccount->getBankCode())->toBe('GXCHPHM2XXX')
        ->and($bankAccount->getAccountNumber())->toBe('09173011987');
});

it('resolves bank account from top-level bank code and account number', function () {
    $bankAccount = bankAccountResolver()->resolve(
        Mockery::mock(Voucher::class),
        fakeBankAccountContact(),
        [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09173011987',
        ],
    );

    expect($bankAccount->getBankCode())->toBe('GXCHPHM2XXX')
        ->and($bankAccount->getAccountNumber())->toBe('09173011987');
});

it('falls back to contact bank account', function () {
    $bankAccount = bankAccountResolver()->resolve(
        Mockery::mock(Voucher::class),
        fakeBankAccountContact('GXCHPHM2XXX:09173011987'),
        [],
    );

    expect($bankAccount->getBankCode())->toBe('GXCHPHM2XXX')
        ->and($bankAccount->getAccountNumber())->toBe('09173011987');
});

it('throws when bank account cannot be resolved', function () {
    bankAccountResolver()->resolve(
        Mockery::mock(Voucher::class),
        fakeBankAccountContact(),
        [],
    );
})->throws(RuntimeException::class, 'Bank account information is required for withdrawal.');
