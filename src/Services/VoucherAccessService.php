<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Carbon\CarbonInterface;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherAccessContract;
use LBHurtado\XChange\Exceptions\VoucherNotFound;
use LBHurtado\XChange\Exceptions\VoucherNotRedeemable;

class VoucherAccessService implements VoucherAccessContract
{
    public function findByCode(string $code): ?Voucher
    {
        $code = trim($code);

        if ($code === '') {
            return null;
        }

        return Voucher::query()
            ->where('code', $code)
            ->first();
    }

    public function findByCodeOrFail(string $code): Voucher
    {
        $voucher = $this->findByCode($code);

        if (! $voucher) {
            throw new VoucherNotFound(sprintf(
                'Voucher [%s] was not found.',
                trim($code)
            ));
        }

        return $voucher;
    }

    public function assertRedeemable(Voucher $voucher): void
    {
        if (method_exists($voucher, 'isExpired') && $voucher->isExpired()) {
            throw new VoucherNotRedeemable(sprintf(
                'Voucher [%s] is expired.',
                (string) $voucher->code
            ));
        }

        if ($this->startsInFuture($voucher)) {
            throw new VoucherNotRedeemable(sprintf(
                'Voucher [%s] is not yet active.',
                (string) $voucher->code
            ));
        }

        if (method_exists($voucher, 'isRedeemed') && $voucher->isRedeemed()) {
            throw new VoucherNotRedeemable(sprintf(
                'Voucher [%s] is not redeemable.',
                (string) $voucher->code
            ));
        }
    }

    protected function startsInFuture(Voucher $voucher): bool
    {
        $startsAt = $voucher->starts_at ?? null;

        return $startsAt instanceof CarbonInterface && $startsAt->isFuture();
    }
}
