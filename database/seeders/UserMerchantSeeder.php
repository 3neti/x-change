<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use LBHurtado\PaymentGateway\Models\Merchant;
use App\Models\{Subscriber, System};
use Illuminate\Database\Seeder;

class UserMerchantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $merchant = Merchant::create( [
            'code' => 'X',
            'name' => "Platform X-Check Account",
            'city' => 'City of Manila',
        ]);
        $system = System::first();
        $system->merchant = $merchant;
        $system->save();

        $merchant = Merchant::create( [
            'code' => 'LBH',
            'name' => "Account of Lester Hurtado",
            'city' => 'Quezon City',
        ]);
        $subscriber = Subscriber::where('email', 'lester@hurtado.ph')->first();
        $subscriber->merchant = $merchant;
        $subscriber->save();
    }
}
