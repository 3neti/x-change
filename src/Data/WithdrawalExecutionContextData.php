<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data;

use Spatie\LaravelData\Data;

class WithdrawalExecutionContextData extends Data
{
    public function __construct(
        public int $claimNumber,
        public int $sliceNumber,
        public string $providerReference,
    ) {}
}
