<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Contact\Classes\BankAccount;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Enums\SettlementRail;
use LBHurtado\Voucher\Models\Voucher;
use Propaganistas\LaravelPhone\PhoneNumber;

// TODO: Standardize SettlementRail enum ownership across money-issuer,
// emi-core, and payment-gateway. This factory currently uses the EMI enum
// because it builds LBHurtado\EmiCore\Data\PayoutRequestData.
class WithdrawalPayoutRequestFactory
{
    public function make(
        Voucher $voucher,
        Contact $contact,
        BankAccount $bankAccount,
        string $providerReference,
        float $amount,
    ): PayoutRequestData {
        $settlementRailEnum = data_get($voucher->instructions, 'cash.settlement_rail');

        $via = $settlementRailEnum instanceof SettlementRail
            ? $settlementRailEnum->value
            : ((float) $amount < 50000 ? 'INSTAPAY' : 'PESONET');

        return PayoutRequestData::from([
            'reference' => $providerReference,
            'amount' => $amount,
            'account_number' => $bankAccount->getAccountNumber(),
            'bank_code' => $bankAccount->getBankCode(),
            'recipient_name' => $contact->name ?: $contact->mobile ?: 'Voucher Recipient',
            'recipient_mobile' => (new PhoneNumber($contact->mobile, 'PH'))->formatE164(),
            'settlement_rail' => $via,
        ]);
    }
}
