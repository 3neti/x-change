<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\EmiCore\Contracts\PhilippineLocalityDataset;
use LBHurtado\EmiCore\Data\PayoutAddressData;

final class FallbackPhilippineLocalityDataset implements PhilippineLocalityDataset
{
    public function version(): string
    {
        return 'x-change-fallback-v1';
    }

    public function candidates(?string $city = null, ?string $region = null): array
    {
        if ($city !== null || $region !== null) {
            return [];
        }

        return [new PayoutAddressData('N/A', 'Manila', 'PH', '1000', 'dataset', $this->version())];
    }
}
