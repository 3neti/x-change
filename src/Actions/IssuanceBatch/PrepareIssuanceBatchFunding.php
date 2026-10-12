<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\IssuanceBatch;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\XChange\Actions\Funding\PrepareOnDemandPayCodeIssuance;
use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Models\IssuanceBatch;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Enums\OnDemandIssuanceFundingBasis;

final readonly class PrepareIssuanceBatchFunding
{
    public function __construct(
        private EstimatePayCodeCost $estimate,
        private PrepareOnDemandPayCodeIssuance $prepare,
    ) {}

    public function handle(Model $issuer, IssuanceBatch $batch, string $idempotencyKey): PayCodeIssuanceFundingOrder
    {
        if ($batch->funding_order_id !== null) {
            return $batch->fundingOrder()->firstOrFail();
        }

        $items = $batch->items()->where('status', 'pending')->get();
        $defaults = VoucherInstructionsData::generateFromScratch()->toArray();
        $pricing = $items->map(fn ($item): PricingEstimateData => $this->estimate->handle([
            ...$defaults,
            'cash' => ['amount' => $item->amount_minor / 100, 'currency' => 'PHP'],
            'voucher_type' => 'redeemable',
            'metadata' => ['flow_type' => 'disbursable'],
        ]));
        $totalValue = $items->sum(fn ($item): float => $item->amount_minor / 100);
        $totalFee = $pricing->sum(fn (PricingEstimateData $quote): float => $quote->total);
        $aggregate = new PricingEstimateData(
            currency: 'PHP',
            total: $totalFee,
            pay_code_value: $totalValue,
            account_debit: $totalValue + $totalFee,
            customer_charge_minor: (int) round($totalFee * 100),
            customer_charge: $totalFee,
            billing_mode: $pricing->first()?->billing_mode ?? 'informational',
        );
        $instructions = [
            ...$defaults,
            'cash' => ['amount' => $totalValue, 'currency' => 'PHP'],
            'voucher_type' => 'redeemable',
            'metadata' => ['flow_type' => 'disbursable', 'issuance_batch_reference' => $batch->reference],
            '_meta' => ['source' => 'public.auto-generate.batch', 'issuance_batch_reference' => $batch->reference],
        ];
        $order = $this->prepare->handle(
            issuer: $issuer,
            instructions: $instructions,
            pricing: $aggregate,
            idempotencyKey: $idempotencyKey,
            fundingBasis: OnDemandIssuanceFundingBasis::FullAmount,
        );
        $batch->forceFill(['funding_order_id' => $order->getKey(), 'status' => 'awaiting_payment'])->save();

        return $order;
    }
}
