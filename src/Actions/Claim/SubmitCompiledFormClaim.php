<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Claim;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Data\Claims\ClaimApprovalInitiationResultData;
use LBHurtado\XChange\Data\PreparedCompiledClaimData;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;
use LBHurtado\XChange\Support\Claim\ClaimEvidenceSynchronizer;

final class SubmitCompiledFormClaim
{
    public function __construct(
        protected BuildCompiledFormClaimPayload $buildPayload,
        protected ClaimEvidenceSynchronizer $evidenceSynchronizer,
        protected SubmitPayCodeClaim $submitPayCodeClaim,
    ) {}

    public function handle(
        Voucher $voucher,
        PreparedCompiledClaimData $prepared,
    ): SubmitPayCodeClaimResultData|ClaimApprovalInitiationResultData {
        $payload = $this->buildPayload->handle(
            voucher: $voucher,
            prepared: $prepared,
        );

        $this->evidenceSynchronizer->sync($payload);

        return $this->submitPayCodeClaim->handle($voucher, $payload);
    }
}
