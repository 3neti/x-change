<?php

namespace LBHurtado\Voucher\Pipelines\GeneratedVouchers;

use LBHurtado\Voucher\Services\MintCash;
use Illuminate\Support\Facades\Log;
use Closure;

class CreateCashEntities
{
    public function handle($vouchers, Closure $next)
    {
        Log::debug('[CreateCashEntities] Starting to mint cash for vouchers.', [
            'total_vouchers' => $vouchers->count(),
        ]);

        $vouchers->each(function ($voucher) {
            Log::debug('[CreateCashEntities] Minting cash for voucher.', [
                'voucher_id' => $voucher->id,
                'voucher_code' => $voucher->code,
            ]);
            MintCash::run($voucher);
            Log::debug('[CreateCashEntities] Minted cash successfully.', [
                'voucher_id' => $voucher->id,
            ]);
        });
        Log::debug('[CreateCashEntities] Finished processing all vouchers.');

        return $next($vouchers);
    }
}
