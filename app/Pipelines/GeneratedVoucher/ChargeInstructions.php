<?php

namespace App\Pipelines\GeneratedVoucher;

use App\Repositories\InstructionItemRepository;
use Illuminate\Support\Facades\Log;
use Closure;

class ChargeInstructions
{
    public function handle($voucher, Closure $next)
    {
        $repository = app(InstructionItemRepository::class);
        $owner = $voucher->owner;

        // 🛑 Bail out if owner or wallet is missing
        if (!$owner || !$owner->wallet) {
            Log::warning('[ChargeInstructions] No wallet found for voucher owner.', [
                'voucher' => $voucher->code,
                'owner_id' => $owner?->id,
            ]);
            return $next($voucher);
        }

        $totalCharge = 0;
        $chargeCount = 0;

        // 💡 Retrieve all chargeable instruction items
        $items = $repository->all();

        Log::debug('[ChargeInstructions] Starting charge evaluation...', [
            'voucher' => $voucher->code,
            'owner_id' => $owner->id,
            'instruction_items' => $items->count(),
        ]);

        foreach ($items as $item) {
            $value = traverse($voucher, $item->index);

            Log::debug("[ChargeInstructions] Evaluating index: {$item->index}", [
                'raw_value' => $value,
                'price' => $item->price,
                'type' => gettype($value),
            ]);

            $isTruthyString = is_string($value) && trim($value) !== '';
            $isTruthyBoolean = is_bool($value) && $value === true;
            $shouldCharge = ($isTruthyString || $isTruthyBoolean) && $item->price > 0;

            Log::debug("[ChargeInstructions] Charge decision", [
                'should_charge' => $shouldCharge,
                'is_truthy_string' => $isTruthyString,
                'is_truthy_boolean' => $isTruthyBoolean,
                'price_check' => $item->price > 0,
            ]);

            if ($shouldCharge) {
                $label = $item->meta['description'] ?? $item->name;

                Log::info("[ChargeInstructions] Charging for index: {$item->index}", [
                    'value' => $value,
                    'price' => $item->price,
                    'label' => $label,
                ]);

                $owner->pay($item);

                $totalCharge += $item->price;
                $chargeCount++;
            }
        }

        // 🧾 Final summary log
        Log::info('[ChargeInstructions] Total charge applied for voucher instructions', [
            'voucher' => $voucher->code,
            'owner_id' => $owner->id,
            'total' => $totalCharge,
            'items_charged' => $chargeCount,
        ]);

        return $next($voucher);
    }
}
