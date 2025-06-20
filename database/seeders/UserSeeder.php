<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\{Subscriber, System};
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        System::create([
            'name' => 'Lester Hurtado',
            'email' => 'admin@disburse.cash',
            'workos_id' => 'user_01JY358RBN6PB8Y238MQQBBVGT',
            'avatar' => '',
        ]);

        Subscriber::create([
            'name' => 'Lester Hurtado',
            'email' => 'lester@hurtado.ph',
            'workos_id' => 'user_01JVRE7HF8244WZBNX18SWS5DG',
            'avatar' => 'https://workoscdn.com/images/v1/SWYo_esN8VqHMcvV6Z1SQZ0c8cAmKIr4AT_cKrzmICA',
        ]);
    }
}
