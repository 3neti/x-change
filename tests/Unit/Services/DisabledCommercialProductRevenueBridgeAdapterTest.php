<?php

declare(strict_types=1);

use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use LBHurtado\XChange\Services\Commercial\DisabledCommercialProductRevenueBridgeAdapter;

it('refuses Product revenue movement until the host commissions an adapter', function (): void {
    $adapter = new DisabledCommercialProductRevenueBridgeAdapter;

    expect(fn (): array => $adapter->post(['status' => 'planned']))
        ->toThrow(CommercialSaleConflict::class, 'movement is disabled');
});

it('refuses Product revenue reversal until the host commissions an adapter', function (): void {
    $adapter = new DisabledCommercialProductRevenueBridgeAdapter;

    expect(fn (): array => $adapter->reverse(['status' => 'planned']))
        ->toThrow(CommercialSaleConflict::class, 'reversal is disabled');
});
