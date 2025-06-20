<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Subscriber;

class SubscriberFactory extends UserFactory
{
    protected $model = Subscriber::class;

    public function definition(): array
    {

        return array_merge(parent::definition(), [
            'type'                 => 'subscriber',
        ]);
    }
}
