<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(UserSeeder::class);
        $this->call(UserWalletSeeder::class);
        $this->call(UserMerchantSeeder::class);
        $this->call(UserChannelsSeeder::class);
        $this->call(InstructionItemSeeder::class);
    }
}
