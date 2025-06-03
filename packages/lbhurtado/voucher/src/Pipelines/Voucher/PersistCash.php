<?php

namespace LBHurtado\Voucher\Pipelines\Voucher;

use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Models\Cash;
use Closure;

class PersistCash
{
    public function handle($voucher, Closure $next)
    {
        $instructions = $voucher->instructions;
        $cash = Cash::create([
            'amount' => $instructions->cash->amount,
            'currency' => $instructions->cash->currency,
            'meta' => ['notes' => 'change this'],
        ]);
        $entities = ['cash' => $cash];
        $voucher->addEntities(...$entities);
        Log::info('Cash entity persisted.');

        return $next($voucher);
    }
}
