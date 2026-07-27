<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Funding;

final readonly class FundingTransferCheckResultData
{
    public function __construct(
        public string $status,
        public string $message,
        public bool $credited,
        public ?string $providerTransactionId = null,
    ) {}
}
