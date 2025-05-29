<?php

namespace LBHurtado\Voucher\Pipelines;

use Illuminate\Support\Facades\Log;
use Closure;

class ApplyUsageLimits
{
    public function handle($vouchers, Closure $next)
    {
        // @todo Implement global or per-user voucher issuance caps
        Log::info('Applying usage limits to generated vouchers.');

        // Example: reject if voucher creator exceeded quota (to be implemented)

        return $next($vouchers);
    }
}
