<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PublicIssuance;

use LBHurtado\Voucher\Models\Voucher;

final readonly class PublicIssuanceVoucherActionPolicy
{
    /**
     * @return array{can_extend: bool, can_cancel: bool, can_expire: bool, can_request_refund: bool}
     */
    public function permissions(Voucher $voucher): array
    {
        $unclaimed = $voucher->redeemed_at === null;
        $active = $voucher->state?->value === 'active' && $unclaimed && $voucher->closed_at === null;
        $unexpired = $voucher->expires_at === null || $voucher->expires_at->isFuture();

        return [
            'can_extend' => $active && $voucher->expires_at?->isFuture() === true,
            'can_cancel' => $active && $unexpired,
            'can_expire' => $active && $unexpired,
            'can_request_refund' => in_array($voucher->state?->value, ['expired', 'cancelled'], true) && $unclaimed,
        ];
    }
}
