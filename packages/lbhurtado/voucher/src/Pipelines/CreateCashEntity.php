<?php

namespace LBHurtado\Voucher\Pipelines;

use LBHurtado\Voucher\Models\Cash;
use Closure;

class CreateCashEntity
{
    public function handle($vouchers, Closure $next)
    {
        $vouchers->each(function ($voucher) {
            $instructions = $voucher->metadata['instructions'] ?? [];

            if (isset($instructions['cash']['amount'], $instructions['cash']['currency'])) {
                // Create the Cash entity for this voucher
                $cash = Cash::create([
                    'amount' => $instructions['cash']['amount'],
                    'currency' => $instructions['cash']['currency'],
                    'meta' => ['dispatched_by' => 'VouchersGenerated'],
                ]);

                // Associate the Cash entity with the Voucher
                $entities = ['cash' => $cash];
                $voucher->addEntities(...$entities);
            }
        });

        return $next($vouchers);
    }
}
