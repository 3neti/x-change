<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\CommercialProductRevenueMovementContract;
use LBHurtado\XChange\Services\Commercial\GovernedCommercialProductRevenueBridgeAdapter;

it('fails closed while the Product bridge is disabled', function (): void {
    config()->set('x-change.commercial.product_revenue_bridge.enabled', false);

    expect(fn (): array => (new GovernedCommercialProductRevenueBridgeAdapter)->post(['transfers' => [[]]]))
        ->toThrow('movement is disabled');
});

it('delegates planned movements only when explicitly enabled', function (): void {
    config()->set('x-change.commercial.product_revenue_bridge.enabled', true);
    $movement = new class implements CommercialProductRevenueMovementContract
    {
        public function post(array $transfer): array
        {
            return ['operation' => 'post', 'amount_minor' => $transfer['amount_minor']];
        }

        public function reverse(array $transfer): array
        {
            return ['operation' => 'reverse', 'amount_minor' => $transfer['amount_minor']];
        }
    };
    $adapter = new GovernedCommercialProductRevenueBridgeAdapter($movement);

    expect($adapter->post(['transfers' => [['amount_minor' => 800]]]))
        ->toBe([['operation' => 'post', 'amount_minor' => 800]])
        ->and($adapter->reverse(['transfers' => [['amount_minor' => 800]]]))
        ->toBe([['operation' => 'reverse', 'amount_minor' => 800]]);
});
