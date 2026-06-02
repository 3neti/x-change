<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Claim;

use LBHurtado\XChange\Data\CompiledClaimPreparationResult;

final class StorePreparedCompiledClaim
{
    public function handle(CompiledClaimPreparationResult $prepared): array
    {
        $payload = [
            'code' => $prepared->submission?->code,
            'voucher_id' => $prepared->voucher?->getKey(),
            'inputs' => $prepared->submission?->inputs ?? [],
        ];

        session()->flash('compiled_claim_prepared', $payload);

        return $payload;
    }
}
