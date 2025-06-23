<?php

namespace LBHurtado\ModelInput\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use LBHurtado\ModelInput\Models\Input;

class InputFactory extends Factory
{
    protected $model = Input::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'value' => $this->faker->phoneNumber(),
        ];
    }
}
