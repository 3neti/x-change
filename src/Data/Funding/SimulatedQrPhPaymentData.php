<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Funding;

use LBHurtado\XChange\Models\SimulatedFundingTransaction;
use Spatie\LaravelData\Data;

class SimulatedQrPhPaymentData extends Data
{
    public function __construct(
        public SimulatedFundingTransaction $transaction,
        public string $rawBody,
        public string $signature,
    ) {}
}
