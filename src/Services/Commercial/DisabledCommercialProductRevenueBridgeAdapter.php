<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\XChange\Contracts\CommercialProductRevenueBridgeAdapterContract;
use LBHurtado\XChange\Exceptions\CommercialSaleConflict;

final class DisabledCommercialProductRevenueBridgeAdapter implements CommercialProductRevenueBridgeAdapterContract
{
    public function post(array $plan): array
    {
        throw new CommercialSaleConflict(
            'Commercial Product revenue movement is disabled until the host commissions its wallet adapter.',
        );
    }

    public function reverse(array $plan): array
    {
        throw new CommercialSaleConflict(
            'Commercial Product revenue reversal is disabled until the host commissions its wallet adapter.',
        );
    }
}
