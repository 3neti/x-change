<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Bavix\Wallet\Interfaces\ProductInterface;
use LBHurtado\XChange\Contracts\CommercialProductResolverContract;
use RuntimeException;

final readonly class InstructionCommercialProductResolver implements CommercialProductResolverContract
{
    public function resolve(string $recipientReference): ProductInterface
    {
        $index = config("x-change.revenue.commercial_product_map.{$recipientReference}");

        if (! is_string($index) || trim($index) === '') {
            throw new RuntimeException("No instruction Product mapping exists for [{$recipientReference}].");
        }

        $model = (string) config('x-change.revenue.instruction_item_model');
        $product = $model::query()->where('index', trim($index))->first();

        if (! $product instanceof ProductInterface) {
            throw new RuntimeException("Instruction Product [{$index}] is unavailable.");
        }

        return $product;
    }
}
