<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data;

use LBHurtado\Contact\Classes\BankAccount;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Redemption\WithdrawPayCodeResultData;

class WithdrawalPipelineContextData
{
    public function __construct(
        public Voucher $voucher,
        public array $payload,
        public ?Contact $contact = null,
        public ?float $withdrawAmount = null,
        public ?BankAccount $bankAccount = null,
        public ?PayoutRequestData $payoutRequest = null,
        public ?WithdrawalDisbursementExecutionData $disbursement = null,
        public ?WithdrawalWalletSettlementData $settlement = null,
        public ?WithdrawPayCodeResultData $result = null,
        public ?int $sliceNumber = null,
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

    public function withDisbursement(WithdrawalDisbursementExecutionData $disbursement): self
    {
        $this->disbursement = $disbursement;

        return $this;
    }

    public function withSliceNumber(int $sliceNumber): self
    {
        $this->sliceNumber = $sliceNumber;

        return $this;
    }

    public function withSettlement(WithdrawalWalletSettlementData $settlement): self
    {
        $this->settlement = $settlement;

        return $this;
    }

    public function withResult(WithdrawPayCodeResultData $result): self
    {
        $this->result = $result;

        return $this;
    }
}
