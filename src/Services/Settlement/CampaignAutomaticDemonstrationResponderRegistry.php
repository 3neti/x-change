<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use LBHurtado\XChange\Contracts\CampaignAutomaticDemonstrationResponderContract;
use LogicException;

final class CampaignAutomaticDemonstrationResponderRegistry
{
    /** @var array<string, CampaignAutomaticDemonstrationResponderContract> */
    private array $responders = [];

    /** @param iterable<CampaignAutomaticDemonstrationResponderContract> $responders */
    public function __construct(iterable $responders)
    {
        foreach ($responders as $responder) {
            $driverId = trim($responder->driverId());
            $driverVersion = trim($responder->driverVersion());

            if ($driverId === '' || $driverVersion === '') {
                throw new LogicException(
                    'Campaign automatic demonstration responders must declare an ID and version.',
                );
            }

            $key = $this->key($driverId, $driverVersion);

            if (isset($this->responders[$key])) {
                throw new LogicException(
                    "Multiple campaign automatic demonstration responders are registered for [{$key}].",
                );
            }

            $this->responders[$key] = $responder;
        }
    }

    public function supports(string $driverId, string $driverVersion): bool
    {
        return isset($this->responders[$this->key($driverId, $driverVersion)]);
    }

    public function for(
        string $driverId,
        string $driverVersion,
    ): CampaignAutomaticDemonstrationResponderContract {
        $key = $this->key($driverId, $driverVersion);

        return $this->responders[$key]
            ?? throw new LogicException(
                "Campaign automatic demonstration responder [{$key}] is unavailable.",
            );
    }

    private function key(string $driverId, string $driverVersion): string
    {
        return trim($driverId).'@'.trim($driverVersion);
    }
}
