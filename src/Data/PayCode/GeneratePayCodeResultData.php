<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\PayCode;

use LBHurtado\XChange\Data\DebitData;
use LBHurtado\XChange\Data\IssuerData;
use LBHurtado\XChange\Data\PayCodeLinksData;
use LBHurtado\XChange\Data\PricingEstimateData;
use Spatie\LaravelData\Data;

class GeneratePayCodeResultData extends Data
{
    /**
     * @param  array<string, mixed>  $wallet
     */
    public function __construct(
        public mixed $voucher_id,
        public string $code,
        public int|float|string $amount,
        public string $currency,
        public IssuerData $issuer,
        public PricingEstimateData $cost,
        public array $wallet,
        public DebitData $debit,
        public PayCodeLinksData $links,
    ) {}
}
