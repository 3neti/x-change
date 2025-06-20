<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\{Subscriber, System};
use Illuminate\Database\Seeder;

class UserChannelsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $system = System::first();
        $system->mobile = '09187251991';
        $system->save();

        $subscriber = Subscriber::where('email', 'lester@hurtado.ph')->first();
        $subscriber->mobile = '09173011987';
        $subscriber->save();
    }
}
