<?php

namespace LBHurtado\Voucher\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\Voucher\Models\Cash;

class CashFactory extends Factory
{
    protected $model = Cash::class;

    public function definition(): array
    {
        return [
            'amount' => $this->faker->numberBetween(100, 10000),
            'currency' => 'PHP',
            'meta' => ['notes' => $this->faker->sentence ],
        ];
    }

    public function forReference(Model $reference): static
    {
        return $this->state(fn () => [
            'reference_type' => $reference::class,
            'reference_id' => $reference->getKey(),
        ]);
    }
}
