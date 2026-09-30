<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Data\Funding\OnDemandIssuanceMismatchData;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;

final readonly class ClassifyOnDemandIssuanceFundingMismatch
{
    public function __construct(
        private TransitionPayCodeIssuanceFundingOrder $transition,
    ) {}

    public function handle(
        FundingIntent $intent,
        ProviderFundingObservation $observation,
        bool $duplicateEvidence = false,
    ): OnDemandIssuanceMismatchData {
        $order = PayCodeIssuanceFundingOrder::query()
            ->where('funding_intent_id', $intent->getKey())
            ->firstOrFail();
        $classification = $this->classification($order, $observation, $duplicateEvidence);

        if (in_array($order->status, [
            PayCodeIssuanceFundingOrderStatus::AwaitingPayment,
            PayCodeIssuanceFundingOrderStatus::PayerAcknowledged,
        ], true)) {
            $order = $this->transition->handle(
                order: $order,
                status: PayCodeIssuanceFundingOrderStatus::Verifying,
                eventType: 'provider_evidence_found',
                actorType: self::class,
                actorId: $intent->provider_code,
                metadata: $classification->details,
            );
        }

        if ($order->status === PayCodeIssuanceFundingOrderStatus::Verifying) {
            $this->transition->handle(
                order: $order,
                status: $classification->status,
                eventType: $classification->eventType,
                actorType: self::class,
                actorId: $intent->provider_code,
                attributes: ['attention_at' => now()],
                metadata: $classification->details,
            );
        }

        return $classification;
    }

    private function classification(
        PayCodeIssuanceFundingOrder $order,
        ProviderFundingObservation $observation,
        bool $duplicateEvidence,
    ): OnDemandIssuanceMismatchData {
        $details = [
            'observed_amount_minor' => $observation->gross_amount_minor,
            'expected_amount_minor' => $order->expected_payment_minor,
            'currency' => $observation->currency,
            'provider_observation_id' => (int) $observation->getKey(),
        ];

        if ($duplicateEvidence) {
            return new OnDemandIssuanceMismatchData(
                status: PayCodeIssuanceFundingOrderStatus::PaymentAmbiguous,
                reasonCode: 'on_demand_issuance_duplicate_evidence',
                eventType: 'duplicate_provider_evidence_rejected',
                details: $details,
            );
        }

        if ($observation->provider_status === 'settled'
            && $observation->currency === $order->currency
            && $observation->gross_amount_minor < $order->expected_payment_minor) {
            return new OnDemandIssuanceMismatchData(
                status: PayCodeIssuanceFundingOrderStatus::Underfunded,
                reasonCode: 'on_demand_issuance_underpayment',
                eventType: 'payment_underfunded',
                details: $details,
            );
        }

        if ($observation->provider_status === 'settled'
            && $observation->currency === $order->currency
            && $observation->gross_amount_minor > $order->expected_payment_minor) {
            return new OnDemandIssuanceMismatchData(
                status: PayCodeIssuanceFundingOrderStatus::PaymentAmbiguous,
                reasonCode: 'on_demand_issuance_excess_payment',
                eventType: 'payment_excess_requires_review',
                details: $details,
            );
        }

        return new OnDemandIssuanceMismatchData(
            status: PayCodeIssuanceFundingOrderStatus::PaymentAmbiguous,
            reasonCode: 'on_demand_issuance_provider_evidence_mismatch',
            eventType: 'provider_evidence_requires_review',
            details: $details,
        );
    }
}
