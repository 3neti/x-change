<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\InstructionItem;
use Illuminate\Database\Seeder;

class InstructionItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = config('x-change.pricelist', []);

        foreach ($items as $index => $data) {
            InstructionItem::firstOrCreate(
                ['index' => $index],
                InstructionItem::attributesFromIndex($index, [
                    'price' => $data['price'],
                    'currency' => 'PHP',
                    'meta' => ['description' => $data['description']],
                ])
            );
        }
    }
}
