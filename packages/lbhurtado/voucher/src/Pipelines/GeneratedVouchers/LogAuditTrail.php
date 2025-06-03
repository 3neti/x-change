<?php

namespace LBHurtado\Voucher\Pipelines\GeneratedVouchers;

use Closure;
use Illuminate\Support\Facades\Log;

class LogAuditTrail
{
    public function handle($vouchers, Closure $next)
    {
        foreach ($vouchers as $voucher) {
            Log::info('Voucher generated', [
                'voucher_id' => $voucher->id,
                'code' => $voucher->code,
                'metadata' => $voucher->metadata,
            ]);
        }

        return $next($vouchers);
    }
}
