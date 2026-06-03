<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Claim;

use LBHurtado\XChange\Data\PreparedCompiledClaimData;
use LBHurtado\XChange\Support\Claim\CompiledClaimSessionKeys;

final class ReadPreparedCompiledClaim
{
    public function handle(bool $forget = false): ?PreparedCompiledClaimData
    {
        $payload = session()->get(CompiledClaimSessionKeys::PREPARED);

        if (! is_array($payload)) {
            return null;
        }

        $prepared = PreparedCompiledClaimData::fromSessionPayload($payload);

        if ($prepared && $forget) {
            session()->forget(CompiledClaimSessionKeys::PREPARED);
        }

        return $prepared;
    }
}
