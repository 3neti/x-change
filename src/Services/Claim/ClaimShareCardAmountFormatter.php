<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use Brick\Money\Money;
use LBHurtado\Voucher\Models\Voucher;

final class ClaimShareCardAmountFormatter
{
    public function format(Voucher $voucher): string
    {
        $amount = data_get($voucher, 'cash.amount');
        $currency = strtoupper((string) data_get(
            $voucher,
            'cash.currency',
            'PHP',
        ));
        $value = $amount instanceof Money
            ? $amount->getAmount()->toFloat()
            : (is_numeric($amount) ? (float) $amount : 0.0);
        $symbol = $currency === 'PHP' ? '₱' : $currency.' ';

        return $symbol.number_format($value, 2);
    }
}
