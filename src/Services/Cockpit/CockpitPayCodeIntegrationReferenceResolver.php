<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Models\CampaignDeliveryAttempt;
use Throwable;

final class CockpitPayCodeIntegrationReferenceResolver
{
    /**
     * @return array<int, string>
     */
    public function feedbackDeliveryIds(string $code): array
    {
        try {
            return CampaignDeliveryAttempt::query()
                ->whereHas('fulfillment', fn ($query) => $query->where('pay_code', $code))
                ->with('events')
                ->get()
                ->flatMap(fn (CampaignDeliveryAttempt $attempt): array => $attempt->events
                    ->pluck('metadata.feedback_delivery_id')
                    ->filter(fn (mixed $deliveryId): bool => is_string($deliveryId) && trim($deliveryId) !== '')
                    ->map(fn (string $deliveryId): string => trim($deliveryId))
                    ->all())
                ->unique()
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }
}
