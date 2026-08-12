<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts\Claim;

use Illuminate\Contracts\Auth\Authenticatable;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Claim\ClaimSurfaceData;

interface ClaimSurfaceResolverContract
{
    public function resolve(Voucher $voucher, ?Authenticatable $user): ClaimSurfaceData;
}
