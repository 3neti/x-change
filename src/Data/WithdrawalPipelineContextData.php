<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data;

use LBHurtado\Contact\Classes\BankAccount;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\Voucher\Models\Voucher;

class WithdrawalPipelineContextData
{
    public function __construct(
        public Voucher $voucher,
        public array $payload,
        public ?Contact $contact = null,
        public ?float $withdrawAmount = null,
        public ?BankAccount $bankAccount = null,
        public ?PayoutRequestData $payoutRequest = null,

    ) {}

    public function withContact(Contact $contact): self
    {
        $this->contact = $contact;

        return $this;
    }

    public function withWithdrawAmount(float $withdrawAmount): self
    {
        $this->withdrawAmount = $withdrawAmount;

        return $this;
    }

    public function withBankAccount(BankAccount $bankAccount): self
    {
        $this->bankAccount = $bankAccount;

        return $this;
    }

    public function withPayoutRequest(PayoutRequestData $payoutRequest): self
    {
        $this->payoutRequest = $payoutRequest;

        return $this;
    }
}
