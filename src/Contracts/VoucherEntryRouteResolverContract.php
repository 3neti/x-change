<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;

interface VoucherEntryRouteResolverContract
{
    /**
     * Expected values for now:
     * - disburse
     * - withdraw
     * - disburse_rejected
     */
    public function resolve(Voucher $voucher, array $context = []): string;
}
