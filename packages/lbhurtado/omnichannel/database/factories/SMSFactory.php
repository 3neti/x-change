<?php

namespace LBHurtado\OmniChannel\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use LBHurtado\OmniChannel\Models\SMS;

class SMSFactory extends Factory
{
    protected $model = SMS::class;

    public function definition(): array
    {
        return [
            'from' => '0917825' . $this->faker->numberBetween(1000,9999),
            'to' => '0917301' . $this->faker->numberBetween(1000,9999),
            'message' => $this->faker->sentence()
        ];
    }
}
