<?php

namespace LBHurtado\Voucher\Pipelines\GeneratedVouchers;

use LBHurtado\Voucher\Services\MintCash;
use Closure;

class CreateCashEntities
{
    public function handle($vouchers, Closure $next)
    {
        $vouchers->each(function ($voucher) {
            MintCash::run($voucher);
        });

        return $next($vouchers);
    }
}
