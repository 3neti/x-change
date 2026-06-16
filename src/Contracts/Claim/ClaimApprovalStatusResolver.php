<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts\Claim;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Claims\ApprovalStatusData;

interface ClaimApprovalStatusResolver
{
    public function resolve(Voucher $voucher): ?ApprovalStatusData;
}
