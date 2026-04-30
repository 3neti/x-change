<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Exceptions\VoucherCannotCollect;
use LBHurtado\XChange\Exceptions\VoucherCannotDisburse;

class VoucherCapabilityGuard
{
    public function __construct(
        protected VoucherFlowCapabilityResolverContract $resolver,
    ) {}

    public function ensureCanCollect(Voucher $voucher): void
    {
        $capabilities = $this->resolver->resolve($voucher);

        if (! $capabilities->can_collect) {
            throw VoucherCannotCollect::forVoucher($voucher, $capabilities);
        }
    }

    public function ensureCanDisburse(Voucher $voucher): void
    {
        $capabilities = $this->resolver->resolve($voucher);

        if (! $capabilities->can_disburse) {
            throw VoucherCannotDisburse::forVoucher($voucher, $capabilities);
        }
    }
}
