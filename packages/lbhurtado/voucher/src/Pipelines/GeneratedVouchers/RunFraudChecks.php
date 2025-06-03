<?php

namespace LBHurtado\Voucher\Pipelines\GeneratedVouchers;

use Closure;
use Illuminate\Support\Facades\Log;

class RunFraudChecks
{
    public function handle($vouchers, Closure $next)
    {
        // @todo Implement fraud detection logic here
        Log::info('Running fraud checks on generated vouchers.');

        // Example: flag vouchers for review based on pattern
        $vouchers->each(function ($voucher) {
            // Placeholder for future fraud detection logic
            // e.g., excessive generation from same IP, suspicious inputs, etc.
        });

        return $next($vouchers);
    }
}
