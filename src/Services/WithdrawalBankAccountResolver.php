<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Arr;
use LBHurtado\Contact\Classes\BankAccount;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Services\Execution\ValueFlowAddressResolver;
use LBHurtado\XChange\Services\Execution\VoucherValueFlowPolicy;
use RuntimeException;

class WithdrawalBankAccountResolver
{
    public function __construct(
        protected VoucherValueFlowPolicy $valueFlows,
        protected ValueFlowAddressResolver $addresses,
    ) {}

    public function resolve(Voucher $voucher, Contact $contact, array $payload): BankAccount
    {
        $destinationAddress = SettlementRepaymentPolicy::forVoucher($voucher)
            ? null : $this->valueFlows->destination($voucher);

        if ($destinationAddress !== null) {
            $destination = $this->addresses->instapayDestination($destinationAddress);
            $bankAccount = BankAccount::fromBankAccount(
                $destination->bankCode.':'.$destination->accountNumber,
            );
            $requested = $this->requestedBankAccount($payload);

            if (! $requested instanceof BankAccount) {
                $redeemer = $this->persistedRedeemer($voucher);
                $persisted = Arr::get($redeemer?->metadata ?? [], 'redemption.bank_account');
                $requested = $this->requestedBankAccount(['bank_account' => $persisted]);
            }

            if ($requested instanceof BankAccount
                && (strtoupper($requested->getBankCode()) !== $bankAccount->getBankCode()
                    || $requested->getAccountNumber() !== $bankAccount->getAccountNumber())) {
                throw new RuntimeException(
                    'Claim destination does not match the voucher value flow destination.',
                );
            }

            return $bankAccount;
        }

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

        if ($bankAccount === null) {
            $redeemer = $this->persistedRedeemer($voucher);
            $fallbackRawBank = Arr::get($redeemer?->metadata ?? [], 'redemption.bank_account');

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

    private function requestedBankAccount(array $payload): ?BankAccount
    {
        $raw = data_get($payload, 'bank_account');

        if (is_array($raw)) {
            $bankCode = data_get($raw, 'bank_code');
            $accountNumber = data_get($raw, 'account_number');

            if (is_string($bankCode) && is_string($accountNumber)
                && trim($bankCode) !== '' && trim($accountNumber) !== '') {
                return BankAccount::fromBankAccount($bankCode.':'.$accountNumber);
            }
        }

        if (is_string($raw) && trim($raw) !== '') {
            return BankAccount::fromBankAccount($raw);
        }

        $bankCode = data_get($payload, 'bank_code');
        $accountNumber = data_get($payload, 'account_number');

        if (is_string($bankCode) && is_string($accountNumber)
            && trim($bankCode) !== '' && trim($accountNumber) !== '') {
            return BankAccount::fromBankAccount($bankCode.':'.$accountNumber);
        }

        return null;
    }

    private function persistedRedeemer(Voucher $voucher): ?object
    {
        if (isset($voucher->redeemer)) {
            return $voucher->redeemer;
        }

        if (! $voucher->exists) {
            return null;
        }

        return $voucher->redeemers()
            ->latest('id')
            ->first();
    }
}
