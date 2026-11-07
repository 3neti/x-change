<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\EmiCore\Contracts\PhilippineLocalityDataset;
use LBHurtado\EmiCore\Data\PayoutAddressData;

final class FallbackPhilippineLocalityDataset implements PhilippineLocalityDataset
{
    public function version(): string
    {
        return 'x-change-fallback-v2';
    }

    public function candidates(?string $city = null, ?string $region = null): array
    {
        if ($city !== null || $region !== null) {
            return [];
        }

        return [
            new PayoutAddressData('Barangay Central', 'Quezon City', 'PH', '1100', 'dataset', $this->version()),
            new PayoutAddressData('Barangay Pinyahan', 'Quezon City', 'PH', '1100', 'dataset', $this->version()),
            new PayoutAddressData('Barangay Sikatuna Village', 'Quezon City', 'PH', '1101', 'dataset', $this->version()),
            new PayoutAddressData('Barangay Bel-Air', 'Makati City', 'PH', '1209', 'dataset', $this->version()),
            new PayoutAddressData('Barangay Mabolo', 'Cebu City', 'PH', '6000', 'dataset', $this->version()),
            new PayoutAddressData('Barangay Apas', 'Cebu City', 'PH', '6000', 'dataset', $this->version()),
            new PayoutAddressData('Barangay 1-A', 'Davao City', 'PH', '8000', 'dataset', $this->version()),
        ];
    }
}
