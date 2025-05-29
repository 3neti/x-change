<?php

namespace LBHurtado\Voucher\Pipelines;

use Closure;

class MarkVouchersAsProcessed
{
    public function handle($vouchers, Closure $next)
    {
        $vouchers->each(function ($voucher) {
            $voucher->processed = true;
            $voucher->save();
        });

        return $next($vouchers);
    }
}
