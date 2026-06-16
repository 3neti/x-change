<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts\Claim;

use LBHurtado\Voucher\Models\Voucher;

interface ClaimApprovalStatusResolver
{
    /**
     * @return array<string, mixed>|null
     */
    public function resolve(Voucher $voucher): ?array;
}
