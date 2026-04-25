<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\MoneyIssuer\Support\BankRegistry;
use LBHurtado\Voucher\Models\Voucher;

class WithdrawalPendingDisbursementRecorder
{
    public function __construct(
        protected BankRegistry $bankRegistry,
    ) {}

    public function record(Voucher $voucher, PayoutRequestData $input, \Throwable $e): void
    {
        $bankName = $this->bankRegistry->getBankName($input->bank_code);

        $metadata = $voucher->metadata ?? [];

        data_set($metadata, 'disbursement', [
            'gateway' => 'unknown',
            'transaction_id' => $input->reference,
            'status' => PayoutStatus::PENDING->value,
            'amount' => $input->amount,
            'currency' => 'PHP',
            'settlement_rail' => $input->settlement_rail,
            'recipient_identifier' => $input->account_number,
            'disbursed_at' => now()->toIso8601String(),
            'recipient_name' => $bankName,
            'payment_method' => 'bank_transfer',
            'error' => $e->getMessage(),
            'requires_reconciliation' => true,
            'metadata' => [
                'bank_code' => $input->bank_code,
                'bank_name' => $bankName,
                'bank_logo' => $this->bankRegistry->getBankLogo($input->bank_code),
                'rail' => $input->settlement_rail,
                'is_emi' => $this->bankRegistry->isEMI($input->bank_code),
            ],
        ]);

        $voucher->metadata = $metadata;
        $voucher->save();
    }
}
