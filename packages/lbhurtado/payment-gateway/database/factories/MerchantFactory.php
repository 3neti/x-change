<?php

namespace LBHurtado\PaymentGateway\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use LBHurtado\PaymentGateway\Models\Merchant;

class MerchantFactory extends Factory
{
    protected $model = Merchant::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->word(),
            'name' => $this->faker->name(),
            'city' => $this->faker->city(),
        ];
    }
}
