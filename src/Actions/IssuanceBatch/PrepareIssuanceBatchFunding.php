<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\IssuanceBatch;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\XChange\Actions\Funding\PrepareOnDemandPayCodeIssuance;
use LBHurtado\XChange\Enums\OnDemandIssuanceFundingBasis;
use LBHurtado\XChange\Models\IssuanceBatch;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;

final readonly class PrepareIssuanceBatchFunding
{
    public function __construct(
        private EstimateIssuanceBatchPricing $pricing,
        private PrepareOnDemandPayCodeIssuance $prepare,
    ) {}

    public function handle(Model $issuer, IssuanceBatch $batch, string $idempotencyKey): PayCodeIssuanceFundingOrder
    {
        if ($batch->funding_order_id !== null) {
            return $batch->fundingOrder()->firstOrFail();
        }

        $items = $batch->items()->where('status', 'pending')->get();
        $aggregate = $this->pricing->handle($items);
        $totalValue = $aggregate->pay_code_value ?? 0;
        $defaults = VoucherInstructionsData::generateFromScratch()->toArray();
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
