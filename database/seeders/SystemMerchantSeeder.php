<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use LBHurtado\PaymentGateway\Models\Merchant;
use Illuminate\Database\Seeder;
use App\Models\System;

class SystemMerchantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $merchant = Merchant::create( [
            'code' => '1',
            'name' => "Platform X-Check Account",
            'city' => 'City of Manila',
        ]);
        $system = System::first();
        $system->merchant = $merchant;
        $system->save();
    }
}
