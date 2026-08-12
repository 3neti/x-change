<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts\Claim;

use Illuminate\Contracts\Auth\Authenticatable;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Claim\ClaimSurfaceViewerData;

interface ClaimViewerResolverContract
{
    public function resolve(?Authenticatable $user, Voucher $voucher): ClaimSurfaceViewerData;
}
