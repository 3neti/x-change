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
            commercial_offering_reference: is_string($estimate['commercial_offering_reference'] ?? null)
                ? $estimate['commercial_offering_reference']
                : null,
            commercial_offering_version: is_numeric($estimate['commercial_offering_version'] ?? null)
                ? (int) $estimate['commercial_offering_version']
                : null,
            commercial_offering_snapshot_hash: is_string($estimate['commercial_offering_snapshot_hash'] ?? null)
                ? $estimate['commercial_offering_snapshot_hash']
                : null,
            commercial_quote_reference: is_string($estimate['commercial_quote_reference'] ?? null)
                ? $estimate['commercial_quote_reference']
                : null,
            catalog_reference: is_string($estimate['catalog_reference'] ?? null)
                ? $estimate['catalog_reference']
                : null,
            catalog_version: is_numeric($estimate['catalog_version'] ?? null)
                ? (int) $estimate['catalog_version']
                : null,
        );
    }
}
