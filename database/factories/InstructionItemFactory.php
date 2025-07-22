<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\InstructionItem;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InstructionItem>
 */
class InstructionItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $index = 'voucher.cash.validation.' . $this->faker->word();

        return InstructionItem::attributesFromIndex($index, [
            'price'    => rand(100, 1000),
            'meta'     => ['description' => $this->faker->sentence()],
        ]);

//        return [
//            'name'     => $this->faker->word(),
//            'index'    => 'voucher.cash.validation.'.$this->faker->word(),
//            'type'     => 'cash',
//            'price'    => rand(100, 1000),
//            'currency' => 'PHP',
//            'meta'     => [],
//        ];
    }
}
