<?php

namespace Database\Factories;

use App\Models\System;

class SystemFactory extends UserFactory
{
    protected $model = System::class;

    public function definition(): array
    {
        return array_merge([
            'type' => 'system',
        ], parent::definition());
    }
}
