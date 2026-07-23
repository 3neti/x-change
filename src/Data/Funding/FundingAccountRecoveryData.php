<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Funding;

use Spatie\LaravelData\Data;

class FundingAccountRecoveryData extends Data
{
    public function __construct(
        public int $requestedAmountMinor,
        public int $recoveredAmountMinor,
        public int $outstandingAmountMinor,
        public ?int $walletTransactionId = null,
        public ?string $walletTransactionUuid = null,
    ) {}
}
