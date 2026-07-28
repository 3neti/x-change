<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Claim\ClaimShareCardData;

interface ClaimShareCardRendererContract
{
    public function render(Voucher $voucher, string $claimUrl): ClaimShareCardData;
}
