<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class CommercialBaselineStorageTime
{
    public static function from(string $instant): CarbonImmutable
    {
        $time = CarbonImmutable::parse($instant)->utc();

        if (DB::connection()->getDriverName() === 'mysql' && $time->equalTo(CarbonImmutable::createFromTimestampUTC(0))) {
            // Keep the signed sentinel untouched; MySQL TIMESTAMP cannot store it in every session time zone.
            return $time->addDay();
        }

        return $time;
    }
}
