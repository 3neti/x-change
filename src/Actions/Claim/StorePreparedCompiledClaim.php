<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Claim;

use LBHurtado\XChange\Data\CompiledClaimPreparationResult;
use LBHurtado\XChange\Support\Claim\CompiledClaimSessionKeys;

final class StorePreparedCompiledClaim
{
    public function handle(CompiledClaimPreparationResult $prepared): array
    {
        $payload = [
            'code' => $prepared->submission?->code,
            'voucher_id' => $prepared->voucher?->getKey(),
            'inputs' => $prepared->submission?->inputs ?? [],
        ];

        session()->flash(CompiledClaimSessionKeys::PREPARED, $payload);

        return $payload;
    }
}
