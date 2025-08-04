<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\{Subscriber, System};
use Illuminate\Database\Seeder;

class SubscriberWalletSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $system = System::first();
        $subscriber = Subscriber::where('email', 'lester@hurtado.ph')->first();
        $system->transferFloat($subscriber, 10_000.00);
        $subscriber->wallet->refreshBalance();
    }
}
