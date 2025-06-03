<?php

namespace LBHurtado\Voucher\Pipelines\GeneratedVouchers;

use Closure;

class ValidateStructure
{
    public function handle($vouchers, Closure $next)
    {
        foreach ($vouchers as $voucher) {
            $instructions = $voucher->metadata['instructions'] ?? [];

            if (!isset($instructions['cash'], $instructions['inputs'], $instructions['feedback'])) {
                throw new \Exception("Voucher metadata incomplete for voucher ID: {$voucher->id}");
            }

            // Additional field-specific checks
            if (!isset($instructions['cash']['amount'], $instructions['cash']['currency'])) {
                throw new \Exception("Cash instruction missing amount or currency for voucher ID: {$voucher->id}");
            }
        }

        return $next($vouchers);
    }
}
