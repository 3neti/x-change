<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Claim;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\PreparedCompiledClaimData;

final class SubmitCompiledFormClaim
{
    /**
     * @return array<string, mixed>
     */
    public function handle(
        Voucher $voucher,
        PreparedCompiledClaimData $prepared,
    ): array {
        return [
            'voucher' => $voucher,
            'prepared' => $prepared,
            'payload' => [
                'source' => 'compiled_form',
                'code' => $prepared->code,
                'voucher_id' => $prepared->voucherId,
                'inputs' => $prepared->inputs,
            ],
        ];
    }
}

