<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Payment;

use DateTimeInterface;

readonly class ObservedPaymentTransactionData
{
    public function __construct(
        public string $providerTransactionId,
        public int $amountMinor,
        public string $currency,
        public string $providerStatus,
        public ?DateTimeInterface $occurredAt = null,
        public ?DateTimeInterface $settledAt = null,
        public ?string $settlementRail = null,
        public ?string $payerName = null,
        public ?string $payerAccountNumber = null,
        public ?string $payerInstitutionCode = null,
        public ?string $payerMobile = null,
    ) {}
}
