<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimApprovalNotificationContract;

class NullClaimApprovalNotificationService implements ClaimApprovalNotificationContract
{
    public function notify(Voucher $voucher, array $workflow): void
    {
        //
    }
}
