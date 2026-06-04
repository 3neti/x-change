<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Claim;

use LBHurtado\XChange\Data\CompiledClaimPreparationResult;
use LBHurtado\XChange\Data\PreparedCompiledClaimData;
use LBHurtado\XChange\Support\Claim\CompiledClaimSessionKeys;

final class StorePreparedCompiledClaim
{
    public function handle(CompiledClaimPreparationResult $result): array
    {
        if (! $result->isValid()) {
            return [];
        }

        $payload = [
            'code' => $result->submission?->code,
            'voucher_id' => $result->voucher?->getKey(),
            'inputs' => $result->submission?->inputs ?? [],
        ];

        $prepared = PreparedCompiledClaimData::fromSessionPayload($payload);

        if (! $prepared) {
            return [];
        }

        session()->flash(
            CompiledClaimSessionKeys::PREPARED,
            $prepared->toArray()
        );

        return $prepared->toArray();
    }
}
