<?php

namespace LBHurtado\PaymentGateway\Data\Netbank\Deposit;

use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Data;

class DepositResponseData extends Data
{
    public function __construct(
        public string $alias,
        public int $amount,
        public string $channel,
        public string $commandId,
        public string $externalTransferStatus,
        public string $operationId,
        public string $productBranchCode,
        public string $recipientAccountNumber,
        public string $recipientAccountNumberBankFormat,
        public string $referenceCode,
        public string $referenceNumber,
        public string $registrationTime,
        public string $remarks,
        public DepositSenderData $sender,
        public string $transferType,
        public DepositMerchantDetailsData|Optional $merchant_details
    ) {}
}
