<?php

namespace LBHurtado\Voucher\Pipelines;

use LBHurtado\Voucher\Actions\MintCash;
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
