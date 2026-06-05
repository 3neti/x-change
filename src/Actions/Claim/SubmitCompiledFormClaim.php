<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Claim;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\PreparedCompiledClaimData;

final class SubmitCompiledFormClaim
{
    public function __construct(
        protected BuildCompiledFormClaimPayload $buildPayload,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(
        Voucher $voucher,
        PreparedCompiledClaimData $prepared,
    ): array {
        return $this->buildPayload->handle(
            voucher: $voucher,
            prepared: $prepared,
        );
    }
}
