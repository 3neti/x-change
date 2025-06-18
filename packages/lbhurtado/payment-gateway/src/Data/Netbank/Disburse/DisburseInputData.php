<?php

namespace LBHurtado\PaymentGateway\Data\Netbank\Disburse;

use LBHurtado\PaymentGateway\Data\SettlementBanksData;
use LBHurtado\Contact\Classes\BankAccount;
use LBHurtado\Voucher\Models\Voucher;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Illuminate\Support\Arr;

class DisburseInputData extends Data
{
    const BANK_ACCOUNT_KEY = 'redemption.bank_account';

    public function __construct(
        public string $reference,
        public int|float $amount,
        public string $account_number,
        public string $bank,
        public string $via
    ) {}

    public static function fromVoucher(
        Voucher $voucher,
        string $via = 'INSTAPAY',
    ): self {
        $cash       = $voucher->cash
            ?? throw new \RuntimeException("Voucher {$voucher->code} has no cash entity");
        $redeemer   = $voucher->redeemer
            ?? throw new \RuntimeException("Voucher {$voucher->code} has no redeemer");
        $contact    = $voucher->contact
            ?? throw new \RuntimeException("Voucher {$voucher->code} has no Contact attached");

        $rawBank = Arr::get($redeemer->metadata, self::BANK_ACCOUNT_KEY, $contact->bank_account);
        $bankAccount = BankAccount::fromBankAccountWithFallback($rawBank, $contact->bank_account);

        return self::from([
            'reference'      => "{$voucher->code}-{$contact->mobile}",
            'amount'         => $cash->amount->getAmount()->toFloat(),
            'account_number' => $bankAccount->getAccountNumber(),
            'bank'           => $bankAccount->getBankCode(),
            'via'            => $via,
        ]);
    }

    public static function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'min:2'],
            'amount' => ['required', 'numeric', 'min:1', 'max:100000'],
            'account_number' => ['required', 'string'],
            'bank' => ['required', 'string', Rule::in(SettlementBanksData::indices())],
            'via' => ['required', 'string', 'in:' . implode(',', config('disbursement.settlement_rails', []))],
        ];
    }
}
