<?php

namespace LBHurtado\Voucher\Pipelines\Voucher;

use Illuminate\Support\Facades\Log;
use Closure;

class EscrowAction
{
    public function handle($voucher, Closure $next)
    {
//        $instructions = $voucher->instructions;
//        $amount = $instructions->cash->amount;
//        $customerWallet = $instructions->customer->wallet;
//        $houseWallet = $instructions->house->wallet;
//
//        // Debit Customer Wallet
//        if (!$customerWallet->debit($amount)) {
//            Log::error("Failed to debit customer wallet for voucher ID: {$voucher->id}.");
//            throw new \RuntimeException('Failed to debit customer wallet.');
//        }
//
//        // Credit House Wallet
//        if (!$houseWallet->credit($amount)) {
//            Log::error("Failed to credit house wallet for voucher ID: {$voucher->id}.");
//            throw new \RuntimeException('Failed to credit house wallet.');
//        }

        Log::info("Funds escrowed from customer to house wallet for voucher ID: {$voucher->id}.");

        return $next($voucher);
    }
}
