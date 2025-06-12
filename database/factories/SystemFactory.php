<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\System;

class SystemFactory extends UserFactory
{
    protected $model = System::class;

    public function definition(): array
    {
        $column     = config('account.system_user.identifier_column');
        $identifier = config('account.system_user.identifier');

        return array_merge(parent::definition(), [
            'type'                 => 'system',
            $column                => $identifier,
        ]);
    }

    /**
     * Configure the factory to deposit initial funds after creation.
     */
    public function configure(): Factory
    {
        return $this->afterCreating(function (System $system) {
            // Deposit 1,000,000.00 into the system wallet
            $system->depositFloat(1_000_000.00);
        });
    }
}
