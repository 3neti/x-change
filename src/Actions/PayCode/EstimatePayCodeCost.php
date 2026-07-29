<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\PayCode;

use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\XChange\Contracts\PricingServiceContract;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Services\VoucherIssuancePayloadNormalizer;

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
        $input = app(VoucherIssuancePayloadNormalizer::class)->normalize($input);
        $instructions = VoucherInstructionsData::from($input);

        $estimate = $this->pricing->estimate($instructions);
        $payCodeValue = round(
            (float) data_get($input, 'cash.amount', 0),
            2,
        );
        $issueCost = round((float) ($estimate['total'] ?? 0), 2);

        return new PricingEstimateData(
            currency: (string) ($estimate['currency'] ?? config('x-change.pricing.currency', 'PHP')),
            base_fee: (float) ($estimate['base_fee'] ?? 0),
            components: (array) ($estimate['components'] ?? []),
            total: $issueCost,
            charges: (array) ($estimate['charges'] ?? []),
            pay_code_value: $payCodeValue,
            account_debit: round($payCodeValue + $issueCost, 2),
        );
    }
}
