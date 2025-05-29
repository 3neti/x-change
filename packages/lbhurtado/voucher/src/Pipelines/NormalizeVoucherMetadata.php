<?php

namespace LBHurtado\Voucher\Pipelines;

use Closure;

class NormalizeVoucherMetadata
{
    public function handle($vouchers, Closure $next)
    {
        $vouchers->each(function ($voucher) {
            $instructions = $voucher->metadata['instructions'] ?? [];

            if (isset($instructions['cash']['amount'])) {
                $instructions['cash']['amount'] = (int) $instructions['cash']['amount'];
            }

            if (isset($instructions['cash']['currency'])) {
                $instructions['cash']['currency'] = strtoupper(trim($instructions['cash']['currency']));
            }

            $voucher->metadata = array_merge($voucher->metadata, ['instructions' => $instructions]);
            $voucher->save();
        });

        return $next($vouchers);
    }
}
