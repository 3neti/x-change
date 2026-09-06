<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Payment;

use Illuminate\Support\Facades\Route;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;

final class PayCodePaymentLinkResolver
{
    public function __construct(
        private readonly VoucherFlowCapabilityResolverContract $capabilities,
    ) {}

    /** @return array{pay: ?string, pay_path: ?string} */
    public function forVoucher(Voucher $voucher): array
    {
        if (! $this->capabilities->resolve($voucher)->can_collect || ! Route::has('x-change.pay.show')) {
            return ['pay' => null, 'pay_path' => null];
        }

        $path = route('x-change.pay.show', ['code' => (string) $voucher->code], false);
        $base = rtrim((string) config('app.url', ''), '/');

        return [
            'pay' => $base === '' ? $path : $base.$path,
            'pay_path' => $path,
        ];
    }
}
