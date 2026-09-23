<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Exceptions;

use RuntimeException;

final class CampaignCoverageDriverUnavailable extends RuntimeException
{
    public static function for(string $driverId, string $driverVersion): self
    {
        return new self(
            "Campaign coverage driver [{$driverId}@{$driverVersion}] is not registered.",
        );
    }
}
