<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\Claim\ClaimApprovalStatusResolver;

final class DefaultClaimApprovalStatusResolver implements ClaimApprovalStatusResolver
{
    /**
     * @return array<string, mixed>|null
     */
    public function resolve(Voucher $voucher): ?array
    {
        return null;
    }
}
