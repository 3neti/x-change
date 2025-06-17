<?php

use LBHurtado\Contact\Classes\BankAccount;

it('can split a valid bank:account string', function () {
    $ba = BankAccount::fromBankAccount('ABC:123456');
    expect($ba->getBankCode())->toBe('ABC')
        ->and($ba->getAccountNumber())->toBe('123456');
});

it('trims whitespace around code and account', function () {
    $ba = BankAccount::fromBankAccount('  XYZ  :  987654321  ');
    expect($ba->getBankCode())->toBe('XYZ')
        ->and($ba->getAccountNumber())->toBe('987654321');
});

it('allows extra colons in the account number', function () {
    $ba = BankAccount::fromBankAccount('BANK:12:34:56');
    expect($ba->getBankCode())->toBe('BANK')
        ->and($ba->getAccountNumber())->toBe('12:34:56');
});

it('throws if no colon present', function () {
    BankAccount::fromBankAccount('NO_COLON_HERE');
})->throws(InvalidArgumentException::class, 'format code:account');

it('throws if code is empty', function () {
    BankAccount::fromBankAccount(':12345');
})->throws(InvalidArgumentException::class, 'cannot be empty');

it('throws if account is empty', function () {
    BankAccount::fromBankAccount('ABC:');
})->throws(InvalidArgumentException::class, 'cannot be empty');
