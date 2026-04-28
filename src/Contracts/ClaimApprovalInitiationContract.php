<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Claims\ClaimApprovalInitiationResultData;

interface ClaimApprovalInitiationContract
{
    public function initiate(Voucher $voucher, array $payload, array $approval): ClaimApprovalInitiationResultData;
}
