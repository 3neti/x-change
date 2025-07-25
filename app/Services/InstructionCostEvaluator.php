<?php

namespace App\Services;

use App\Repositories\InstructionItemRepository;
use Bavix\Wallet\Interfaces\Customer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class InstructionCostEvaluator
{
    public function __construct(
        protected InstructionItemRepository $repository
    ) {}

    public function evaluate(Customer $customer, mixed $source): Collection
    {
        $charges = collect();
        $items = $this->repository->all();

        Log::debug('[InstructionCostEvaluator] Starting evaluation...', [
            'source_type' => get_debug_type($source),
            'instruction_items_count' => $items->count(),
        ]);

        foreach ($items as $item) {
            $value = traverse($source, $item->index);
            $isTruthyString = is_string($value) && trim($value) !== '';
            $isTruthyBoolean = is_bool($value) && $value === true;
            $isTruthyFloat = is_float($value) && $value > 0.0;
            $shouldCharge = ($isTruthyString || $isTruthyBoolean || $isTruthyFloat) && $item->price > 0;

            $price = $item->getAmountProduct($customer);

            Log::debug("[InstructionCostEvaluator] Evaluating index: {$item->index}", [
                'raw_value' => $value,
                'type' => gettype($value),
                'price' => $price,
                'currency' => $item->currency,
                'is_truthy_string' => $isTruthyString,
                'is_truthy_boolean' => $isTruthyBoolean,
                'is_truthy_float' => $isTruthyFloat,
                'should_charge' => $shouldCharge,
            ]);

            if ($shouldCharge) {
                $label = $item->meta['label'] ?? $item->name;

                Log::info("[InstructionCostEvaluator] ✅ Chargeable instruction detected", [
                    'index' => $item->index,
                    'label' => $label,
                    'value' => $value,
                    'price' => $price,
                    'currency' => $item->currency
                ]);

                $charges->push([
                    'item' => $item,
                    'value' => $value,
                    'price' => $price,
                    'currency' => $item->currency,
                    'label' => $label,
                ]);
            }
        }

        Log::info('[InstructionCostEvaluator] Evaluation complete', [
            'total_items_charged' => $charges->count(),
            'total_amount' => $charges->sum('price'),
        ]);

        return $charges;
    }
}
