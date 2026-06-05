<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Claim;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\PreparedCompiledClaimData;
use LBHurtado\XChange\Support\Claim\ClaimEvidenceSynchronizer;

final class SubmitCompiledFormClaim
{
    public function __construct(
        protected BuildCompiledFormClaimPayload $buildPayload,
        protected ClaimEvidenceSynchronizer $evidenceSynchronizer,
    ) {}

    public function handle(
        Voucher $voucher,
        PreparedCompiledClaimData $prepared,
    ): array {
        $payload = $this->buildPayload->handle(
            voucher: $voucher,
            prepared: $prepared,
        );

        $this->evidenceSynchronizer->sync($payload);

        return $payload;
    }
}
