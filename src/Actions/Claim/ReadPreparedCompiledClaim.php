<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Claim;

use LBHurtado\XChange\Data\PreparedCompiledClaimData;

final class ReadPreparedCompiledClaim
{
    public function handle(bool $forget = false): ?PreparedCompiledClaimData
    {
        $payload = session()->get('compiled_claim_prepared');

        if (! is_array($payload)) {
            return null;
        }

        $prepared = PreparedCompiledClaimData::fromSessionPayload($payload);

        if ($prepared && $forget) {
            session()->forget('compiled_claim_prepared');
        }

        return $prepared;
    }
}
