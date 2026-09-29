<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;

final class RecordLateOnDemandIssuancePayment
{
    public function handle(
        FundingIntent $intent,
        ProviderFundingObservation $observation,
    ): ?PayCodeIssuanceFundingOrder {
        return DB::transaction(function () use ($intent, $observation): ?PayCodeIssuanceFundingOrder {
            $order = PayCodeIssuanceFundingOrder::query()
                ->where('funding_intent_id', $intent->getKey())
                ->lockForUpdate()
                ->first();

            if (! $order instanceof PayCodeIssuanceFundingOrder || ! in_array($order->status, [
                PayCodeIssuanceFundingOrderStatus::Cancelled,
                PayCodeIssuanceFundingOrderStatus::Expired,
            ], true)) {
                return null;
            }

            if ($order->late_payment_detected_at !== null) {
                return $order->refresh()->load(['fundingIntent', 'events']);
            }

            $order->forceFill([
                'late_payment_detected_at' => now(),
                'late_payment_disposition' => 'client_funds',
                'amount_lease_active_key' => null,
                'amount_lease_released_at' => now(),
                'version' => $order->version + 1,
            ])->saveQuietly();
            $order->events()->create([
                'sequence' => $order->version,
                'event_type' => 'late_payment_credited_to_client_funds',
                'from_status' => $order->status,
                'to_status' => $order->status,
                'actor_type' => self::class,
                'actor_id' => $intent->provider_code,
                'metadata' => [
                    'provider_observation_id' => $observation->getKey(),
                    'provider_transaction_id' => $observation->provider_transaction_id,
                    'net_amount_minor' => $observation->net_amount_minor,
                    'disposition' => 'client_funds',
                    'amount_lease_release_reason' => 'authoritative_late_payment_matched',
                ],
                'occurred_at' => now(),
            ]);

            return $order->refresh()->load(['fundingIntent', 'events']);
        }, attempts: 5);
    }
}
