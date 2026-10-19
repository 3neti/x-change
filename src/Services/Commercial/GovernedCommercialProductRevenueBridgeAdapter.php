<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\XChange\Contracts\CommercialProductRevenueBridgeAdapterContract;
use LBHurtado\XChange\Contracts\CommercialProductRevenueMovementContract;
use LBHurtado\XChange\Exceptions\CommercialSaleConflict;

final readonly class GovernedCommercialProductRevenueBridgeAdapter implements CommercialProductRevenueBridgeAdapterContract
{
    public function __construct(
        private ?CommercialProductRevenueMovementContract $movement = null,
    ) {}

    public function post(array $plan): array
    {
        return $this->execute('post', $plan);
    }

    public function reverse(array $plan): array
    {
        return $this->execute('reverse', $plan);
    }

    /** @param array<string, mixed> $plan */
    private function execute(string $operation, array $plan): array
    {
        if (! (bool) config('x-change.commercial.product_revenue_bridge.enabled', false)) {
            throw new CommercialSaleConflict(
                'Commercial Product revenue movement is disabled until the host enables its commissioned adapter.',
            );
        }

        if ($this->movement === null) {
            throw new CommercialSaleConflict(
                'Commercial Product revenue movement has no commissioned host adapter.',
            );
        }

        $results = [];

        foreach ((array) ($plan['transfers'] ?? []) as $transfer) {
            $results[] = $this->movement->{$operation}([
                ...$transfer,
                'source_position_reference' => $plan['source_position_reference'] ?? null,
            ]);
        }

        return $results;
    }
}
