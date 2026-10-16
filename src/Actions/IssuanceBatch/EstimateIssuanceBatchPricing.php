<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\IssuanceBatch;

use Illuminate\Support\Collection;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Data\PricingEstimateData;

final readonly class EstimateIssuanceBatchPricing
{
    public function __construct(private EstimatePayCodeCost $estimate) {}

    /**
     * @param  Collection<int, object>  $items
     */
    public function handle(Collection $items): PricingEstimateData
    {
        $defaults = VoucherInstructionsData::generateFromScratch()->toArray();
        $pricing = $items->map(function ($item) use ($defaults): PricingEstimateData {
            $payload = [
                ...$defaults,
                ...(array) ($item->payload ?? []),
            ];

            // Keep this payload assembly aligned with IssueIssuanceBatchPayCodes.
            // Row-level instructions are billable and must be included before payment.
            $payload['cash'] = [
                'amount' => $item->amount_minor / 100,
                'currency' => 'PHP',
            ];
            $payload['voucher_type'] = 'redeemable';
            $payload['metadata'] = [
                ...(array) ($payload['metadata'] ?? []),
                'flow_type' => 'disbursable',
            ];

            return $this->estimate->handle($payload);
        });
        $totalValue = $items->sum(fn ($item): float => $item->amount_minor / 100);
        $totalFee = $pricing->sum(fn (PricingEstimateData $quote): float => $quote->total);
        $totalDebit = $pricing->sum(
            fn (PricingEstimateData $quote): float => $quote->account_debit
                ?? (($quote->pay_code_value ?? 0) + $quote->total),
        );

        return new PricingEstimateData(
            currency: 'PHP',
            total: $totalFee,
            pay_code_value: $totalValue,
            account_debit: $totalDebit,
            customer_charge_minor: (int) round($totalFee * 100),
            customer_charge: $totalFee,
            billing_mode: $pricing->first()?->billing_mode ?? 'informational',
        );
    }
}
