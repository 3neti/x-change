<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Arr;
use LBHurtado\Contact\Classes\BankAccount;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Models\Voucher;
use RuntimeException;

class WithdrawalBankAccountResolver
{
    public function resolve(Voucher $voucher, Contact $contact, array $payload): BankAccount
    {
        $rawBank = data_get($payload, 'bank_account');
        $bankAccount = null;

        if (is_array($rawBank)) {
            $bankCode = data_get($rawBank, 'bank_code');
            $accountNumber = data_get($rawBank, 'account_number');

            if (
                is_string($bankCode) && trim($bankCode) !== ''
                && is_string($accountNumber) && trim($accountNumber) !== ''
            ) {
                $bankAccount = BankAccount::fromBankAccount(
                    trim($bankCode).':'.trim($accountNumber)
                );
            }
        }

        if ($bankAccount === null && is_string($rawBank) && trim($rawBank) !== '') {
            $fallbackBankAccount = is_string($contact->bank_account) && trim($contact->bank_account) !== ''
                ? $contact->bank_account
                : null;

            $bankAccount = $fallbackBankAccount
                ? BankAccount::fromBankAccountWithFallback($rawBank, $fallbackBankAccount)
                : BankAccount::fromBankAccount($rawBank);
        }

        if ($bankAccount === null) {
            $bankCode = data_get($payload, 'bank_code');
            $accountNumber = data_get($payload, 'account_number');

            if (
                is_string($bankCode) && trim($bankCode) !== ''
                && is_string($accountNumber) && trim($accountNumber) !== ''
            ) {
                $bankAccount = BankAccount::fromBankAccount(
                    trim($bankCode).':'.trim($accountNumber)
                );
            }
        }

        if ($bankAccount === null && property_exists($voucher, 'redeemer') && $voucher->redeemer) {
            $fallbackRawBank = Arr::get($voucher->redeemer->metadata ?? [], 'redemption.bank_account');

            if (is_string($fallbackRawBank) && trim($fallbackRawBank) !== '') {
                $fallbackBankAccount = is_string($contact->bank_account) && trim($contact->bank_account) !== ''
                    ? $contact->bank_account
                    : null;

                $bankAccount = $fallbackBankAccount
                    ? BankAccount::fromBankAccountWithFallback($fallbackRawBank, $fallbackBankAccount)
                    : BankAccount::fromBankAccount($fallbackRawBank);
            }
        }

        if ($bankAccount === null) {
            $fallbackBankAccount = is_string($contact->bank_account) && trim($contact->bank_account) !== ''
                ? $contact->bank_account
                : null;

            if ($fallbackBankAccount) {
                $bankAccount = BankAccount::fromBankAccount($fallbackBankAccount);
            }
        }

        if ($bankAccount === null) {
            throw new RuntimeException('Bank account information is required for withdrawal.');
        }

        return $bankAccount;
    }
}
