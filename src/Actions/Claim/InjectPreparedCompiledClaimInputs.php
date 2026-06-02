<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Claim;

use LBHurtado\XChange\Data\PreparedCompiledClaimData;

final class InjectPreparedCompiledClaimInputs
{
    public function handle(PreparedCompiledClaimData $prepared, array $state = []): array
    {
        return array_replace_recursive($state, [
            'code' => $prepared->code,
            'voucher_id' => $prepared->voucherId,
            'inputs' => $prepared->inputs,
            'compiled_claim' => [
                'code' => $prepared->code,
                'voucher_id' => $prepared->voucherId,
                'inputs' => $prepared->inputs,
            ],
        ]);
    }
}
