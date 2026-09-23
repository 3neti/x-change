<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use LBHurtado\XChange\Contracts\CampaignCoverageDriverContract;
use LBHurtado\XChange\Exceptions\CampaignCoverageDriverUnavailable;
use LogicException;

final class CampaignCoverageDriverRegistry
{
    /** @var array<string, CampaignCoverageDriverContract> */
    private array $drivers = [];

    /** @param iterable<CampaignCoverageDriverContract> $drivers */
    public function __construct(iterable $drivers)
    {
        foreach ($drivers as $driver) {
            $driverId = trim($driver->driverId());
            $driverVersion = trim($driver->driverVersion());

            if ($driverId === '' || $driverVersion === '') {
                throw new LogicException(
                    'Campaign coverage drivers must declare an ID and version.',
                );
            }

            $key = $this->key($driverId, $driverVersion);

            if (isset($this->drivers[$key])) {
                throw new LogicException(
                    "Multiple campaign coverage drivers are registered for [{$key}].",
                );
            }

            $this->drivers[$key] = $driver;
        }
    }

    public function for(string $driverId, string $driverVersion): CampaignCoverageDriverContract
    {
        $key = $this->key($driverId, $driverVersion);

        return $this->drivers[$key]
            ?? throw CampaignCoverageDriverUnavailable::for($driverId, $driverVersion);
    }

    private function key(string $driverId, string $driverVersion): string
    {
        return trim($driverId).'@'.trim($driverVersion);
    }
}
