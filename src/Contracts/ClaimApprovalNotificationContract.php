<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;

interface ClaimApprovalNotificationContract
{
    public function notify(Voucher $voucher, array $workflow): void;
}
