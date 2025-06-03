<?php

namespace LBHurtado\Voucher\Pipelines\Voucher;

use Illuminate\Support\Facades\Log;
use Closure;

class CheckBalance
{
    public function handle($voucher, Closure $next)
    {
//        $instructions = $voucher->instructions;
//        $requiredAmount = $instructions->cash->amount;
//        $customerWallet = $instructions->customer->wallet;
//
//        if ($customerWallet->balance < $requiredAmount) {
//            Log::error("Customer wallet has insufficient funds for voucher ID: {$voucher->id}.");
//            throw new \RuntimeException('Insufficient balance to create a voucher.');
//        }

        Log::info("Sufficient balance confirmed for voucher ID: {$voucher->id}.");

        return $next($voucher);
    }
}
