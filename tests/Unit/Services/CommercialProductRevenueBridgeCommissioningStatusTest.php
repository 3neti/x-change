<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CommercialProductRevenueMovementContract;
use LBHurtado\XChange\Services\Commercial\CommercialProductRevenueBridgeCommissioningStatus;

it('reports a fail-closed bridge as not ready', function (): void {
    config()->set('x-change.commercial.product_revenue_bridge.enabled', false);

    expect(app(CommercialProductRevenueBridgeCommissioningStatus::class)->inspect())
        ->toMatchArray([
            'enabled' => false,
            'movement_adapter_bound' => false,
            'ready' => false,
            'blockers' => ['feature_disabled', 'movement_adapter_not_bound'],
        ]);
});

it('reports readiness only when both host controls are present', function (): void {
    config()->set('x-change.commercial.product_revenue_bridge.enabled', true);
    $movement = new class implements CommercialProductRevenueMovementContract
    {
        public function post(array $transfer): array
        {
            return $transfer;
        }

        public function reverse(array $transfer): array
        {
            return $transfer;
        }
    };

    expect(app(CommercialProductRevenueBridgeCommissioningStatus::class)->inspect($movement))
        ->toMatchArray([
            'enabled' => true,
            'movement_adapter_bound' => true,
            'ready' => true,
            'blockers' => [],
        ]);
});
