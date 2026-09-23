<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use LBHurtado\XChange\Data\Settlement\PolicyCompletionTransportDispositionData;

final class PolicyCompletionTransportDispositionCatalog
{
    public function for(
        string $driverId,
        string $driverVersion,
    ): ?PolicyCompletionTransportDispositionData {
        $transports = config('x-change.settlement.policy_completion.transports', []);
        $manifest = is_array($transports)
            ? ($transports[$driverId.'@'.$driverVersion] ?? null)
            : null;

        if (! is_array($manifest)) {
            return null;
        }

        return PolicyCompletionTransportDispositionData::fromManifest(
            $manifest,
            $driverId,
            $driverVersion,
        );
    }
}
