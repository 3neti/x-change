<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Claim;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimApprovalExecutionContract;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;

final class VerifyClaimApprovalOtp
{
    public function __construct(
        private readonly ClaimApprovalExecutionContract $approval,
    ) {}

    /**
     * Verify the pending claim approval OTP and resume the claim.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(Voucher $voucher, array $payload): SubmitPayCodeClaimResultData
    {
        return $this->approval->verifyOtp($voucher, $payload);
    }
}
