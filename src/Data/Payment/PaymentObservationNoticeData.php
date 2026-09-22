<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Payment;

final readonly class PaymentObservationNoticeData
{
    public function __construct(
        public int $paymentAttemptId,
        public int $transactionId,
        public int $statusId,
        public string $providerStatus,
        public string $reason,
        public string $occurredAt,
    ) {}
}
