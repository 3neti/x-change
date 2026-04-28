<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Redemption\SubmitPayCodeClaimResultData;

interface ClaimApprovalExecutionContract
{
    public function approve(Voucher $voucher, array $payload): SubmitPayCodeClaimResultData;

    public function verifyOtp(Voucher $voucher, array $payload): SubmitPayCodeClaimResultData;
}
