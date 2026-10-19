<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\XChange\Contracts\CommercialProductRevenueMovementContract;

final readonly class CommercialProductRevenueBridgeCommissioningStatus
{
    /** @return array{enabled:bool, movement_adapter_bound:bool, ready:bool, blockers:list<string>} */
    public function inspect(?CommercialProductRevenueMovementContract $movement = null): array
    {
        $enabled = (bool) config('x-change.commercial.product_revenue_bridge.enabled', false);
        $bound = $movement !== null;
        $blockers = [];

        if (! $enabled) {
            $blockers[] = 'feature_disabled';
        }

        if (! $bound) {
            $blockers[] = 'movement_adapter_not_bound';
        }

        return [
            'enabled' => $enabled,
            'movement_adapter_bound' => $bound,
            'ready' => $enabled && $bound,
            'blockers' => $blockers,
        ];
    }
}
