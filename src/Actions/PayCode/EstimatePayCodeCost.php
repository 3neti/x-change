<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\PayCode;

use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\XChange\Contracts\PricingServiceContract;
use LBHurtado\XChange\Data\PricingEstimateData;

class EstimatePayCodeCost
{
    public function __construct(
        protected PricingServiceContract $pricing,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input): PricingEstimateData
    {
        $instructions = VoucherInstructionsData::from($input);

        $estimate = $this->pricing->estimate($instructions);

        return new PricingEstimateData(
            currency: (string) ($estimate['currency'] ?? config('x-change.pricing.currency', 'PHP')),
            base_fee: (float) ($estimate['base_fee'] ?? 0),
            components: (array) ($estimate['components'] ?? []),
            total: (float) ($estimate['total'] ?? 0),
        );
    }
}
