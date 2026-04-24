<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data;

use Spatie\LaravelData\Data;

class WithdrawalWalletSettlementData extends Data
{
    public function __construct(
        public mixed $transfer,
        public float $feeAmount,
        public string $feeStrategy,
    ) {}
}
