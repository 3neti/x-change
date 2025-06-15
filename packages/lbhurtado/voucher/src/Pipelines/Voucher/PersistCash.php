<?php

namespace LBHurtado\Voucher\Pipelines\Voucher;

use Illuminate\Support\Facades\Log;
use LBHurtado\Cash\Models\Cash;
use Closure;

class PersistCash
{
    public function handle($voucher, Closure $next)
    {
        Log::debug('[PersistCash] Starting PersistCash for voucher', [
            'code' => $voucher->code,
            'instructions' => $voucher->instructions->toArray(),
        ]);

        $instructions = $voucher->instructions;
        $amount       = $instructions->cash->amount;
        $currency     = $instructions->cash->currency;

        Log::debug('[PersistCash] Creating Cash record', compact('amount', 'currency'));

        $cash = Cash::create([
            'amount'   => $amount,
            'currency' => $currency,
            'meta'     => ['notes' => 'change this'],
        ]);

        Log::info('[PersistCash] Cash record created', [
            'cash_id'  => $cash->getKey(),
            'amount'   => $cash->amount,
            'currency' => $cash->currency,
        ]);

        $entities = ['cash' => $cash];
        $voucher->addEntities(...$entities);

        Log::debug('[PersistCash] Attached cash entity to voucher', [
            'voucher_code' => $voucher->code,
            'attached'     => array_keys($entities),
        ]);

        return $next($voucher);
    }
}
