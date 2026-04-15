<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Lifecycle;

use Illuminate\Support\Facades\DB;

class DBHelper
{
    public static function instructionPrice(string $index): float
    {
        $row = DB::table('instruction_items')
            ->where('index', $index)
            ->first();

        return $row ? (float) $row->price : 0.0;
    }
}
