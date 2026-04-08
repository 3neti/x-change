<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Redemption\LoadRedemptionCompletionContextResultData;

interface RedemptionCompletionContextContract
{
    public function load(
        Voucher $voucher,
        ?string $referenceId = null,
        ?string $flowId = null,
    ): LoadRedemptionCompletionContextResultData;
}
