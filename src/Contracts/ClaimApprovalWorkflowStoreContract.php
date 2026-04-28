<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;

interface ClaimApprovalWorkflowStoreContract
{
    public function get(Voucher $voucher): ?array;

    public function put(Voucher $voucher, array $workflow): void;

    public function forget(Voucher $voucher): void;
}
