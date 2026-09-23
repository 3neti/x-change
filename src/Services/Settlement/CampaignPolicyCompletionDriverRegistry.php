<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use LBHurtado\XChange\Contracts\CampaignPolicyCompletionDriverContract;
use LBHurtado\XChange\Exceptions\CampaignPolicyCompletionDriverUnavailable;
use LogicException;

final class CampaignPolicyCompletionDriverRegistry
{
    /** @var array<string, CampaignPolicyCompletionDriverContract> */
    private array $drivers = [];

    /** @param iterable<CampaignPolicyCompletionDriverContract> $drivers */
    public function __construct(iterable $drivers)
    {
        foreach ($drivers as $driver) {
            $driverId = trim($driver->driverId());
            $driverVersion = trim($driver->driverVersion());

            if ($driverId === '' || $driverVersion === '') {
                throw new LogicException(
                    'Campaign policy completion drivers must declare an ID and version.',
                );
            }

            $key = $this->key($driverId, $driverVersion);

            if (isset($this->drivers[$key])) {
                throw new LogicException(
                    "Multiple campaign policy completion drivers are registered for [{$key}].",
                );
            }

            $this->drivers[$key] = $driver;
        }
    }

    public function for(
        string $driverId,
        string $driverVersion,
    ): CampaignPolicyCompletionDriverContract {
        $key = $this->key($driverId, $driverVersion);

        return $this->drivers[$key]
            ?? throw CampaignPolicyCompletionDriverUnavailable::for($driverId, $driverVersion);
    }

    private function key(string $driverId, string $driverVersion): string
    {
        return trim($driverId).'@'.trim($driverVersion);
    }
}
