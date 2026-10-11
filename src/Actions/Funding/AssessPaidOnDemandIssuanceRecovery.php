<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Enums\FundingIntentPurpose;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\FundingSettlement;
use LBHurtado\XChange\Models\FundingSuspenseCase;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;

final class AssessPaidOnDemandIssuanceRecovery
{
    public function order(FundingSuspenseCase $case): ?PayCodeIssuanceFundingOrder
    {
        $intent = $case->fundingIntent;

        if (! $intent instanceof FundingIntent) {
            return null;
        }

        return $intent->relationLoaded('issuanceFundingOrder')
            ? $intent->issuanceFundingOrder
            : $intent->issuanceFundingOrder()->first();
    }

    public function eligible(FundingSuspenseCase $case): bool
    {
        $intent = $case->fundingIntent;
        $observation = $case->providerFundingObservation;
        $order = $this->order($case);

        return $intent instanceof FundingIntent
            && $observation instanceof ProviderFundingObservation
            && $order instanceof PayCodeIssuanceFundingOrder
            && $this->matches($case, $intent, $observation, $order);
    }

    public function matches(
        FundingSuspenseCase $case,
        FundingIntent $intent,
        ProviderFundingObservation $observation,
        PayCodeIssuanceFundingOrder $order,
    ): bool {
        return in_array($case->status, ['open', 'monitoring'], true)
            && $case->reason_code === 'on_demand_issuance_provider_evidence_mismatch'
            && (int) $case->funding_intent_id === (int) $intent->getKey()
            && (int) $case->provider_funding_observation_id === (int) $observation->getKey()
            && (int) $order->funding_intent_id === (int) $intent->getKey()
            && $order->issuer_type === $intent->created_by_type
            && (string) $order->issuer_id === (string) $intent->created_by_id
            && $intent->purpose === FundingIntentPurpose::OnDemandIssuance
            && $intent->status === FundingIntentStatus::Suspense
            && (int) $intent->matched_observation_id === (int) $observation->getKey()
            && $order->reserved_client_funds_minor === 0
            && $order->on_demand_amount_minor === $order->required_amount_minor
            && $order->treasury_hold_reference === null
            && $order->funded_at === null
            && $order->voucher_id === null
            && $order->late_payment_detected_at === null
            && data_get($order->metadata, 'provider_reversal') === null
            && in_array($order->status, [
                PayCodeIssuanceFundingOrderStatus::PaymentAmbiguous,
                PayCodeIssuanceFundingOrderStatus::Expired,
            ], true)
            && $observation->provider_code === $intent->provider_code
            && $observation->provider_status === 'settled'
            && $observation->gross_amount_minor === $order->expected_payment_minor
            && $observation->gross_amount_minor === $intent->expected_amount_minor
            && $observation->net_amount_minor >= $order->required_amount_minor
            && $observation->currency === $order->currency
            && $observation->currency === $intent->currency
            && $observation->settled_at !== null
            && $observation->occurred_at !== null
            && $observation->occurred_at->greaterThanOrEqualTo($order->created_at)
            && $observation->occurred_at->lessThanOrEqualTo($order->expires_at)
            && data_get($observation->metadata, 'destination_verified') === true
            && ! FundingSettlement::query()->where('funding_intent_id', $intent->getKey())->exists();
    }
}
