<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;

interface SettlementReadinessGateContract
{
    /**
     * Legacy compatibility method.
     *
     * Existing unit tests and earlier services still call assertReady().
     * New code should prefer ensureReady().
     */
    public function assertReady(Voucher $voucher): void;

    public function ensureReady(
        Voucher $voucher,
        string $gate = 'settleable',
        array $context = [],
    ): void;
}
