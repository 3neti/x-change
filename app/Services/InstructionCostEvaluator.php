<?php

namespace App\Services;

use App\Repositories\InstructionItemRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class InstructionCostEvaluator
{
    public function __construct(
        protected InstructionItemRepository $repository
    ) {}

    public function evaluate(mixed $source): Collection
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
            $shouldCharge = ($isTruthyString || $isTruthyBoolean) && $item->price > 0;

            Log::debug("[InstructionCostEvaluator] Evaluating index: {$item->index}", [
                'raw_value' => $value,
                'type' => gettype($value),
                'price' => $item->price,
                'is_truthy_string' => $isTruthyString,
                'is_truthy_boolean' => $isTruthyBoolean,
                'should_charge' => $shouldCharge,
            ]);

            if ($shouldCharge) {
                $label = $item->meta['label'] ?? $item->name;

                Log::info("[InstructionCostEvaluator] ✅ Chargeable instruction detected", [
                    'index' => $item->index,
                    'label' => $label,
                    'value' => $value,
                    'price' => $item->price,
                ]);

                $charges->push([
                    'item' => $item,
                    'value' => $value,
                    'price' => round($item->price, 2),//improve this, use Price
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
