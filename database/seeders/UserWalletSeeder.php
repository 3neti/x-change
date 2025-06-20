<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\{Subscriber, System};
use Illuminate\Database\Seeder;

class UserWalletSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $system = System::first();
        $system->depositFloat(1_000_000.00);
        $system->wallet->refreshBalance();

        $subscriber = Subscriber::where('email', 'lester@hurtado.ph')->first();
        $system->transferFloat($subscriber, 10_000.00);
        $subscriber->wallet->refreshBalance();
    }
}
