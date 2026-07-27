<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

use Carbon\CarbonImmutable;

enum FundingTransferWindow: string
{
    case Recent = 'recent';
    case LastHour = 'last_hour';
    case Today = 'today';

    public function startsAt(
        CarbonImmutable $now,
        int $automaticCreditWindowMinutes,
    ): CarbonImmutable {
        return match ($this) {
            self::Recent => $now->subMinutes($automaticCreditWindowMinutes),
            self::LastHour => $now->subHour(),
            self::Today => $now->startOfDay(),
        };
    }

    public function label(int $automaticCreditWindowMinutes): string
    {
        return match ($this) {
            self::Recent => "Last {$automaticCreditWindowMinutes} minutes",
            self::LastHour => 'Last hour',
            self::Today => 'Today',
        };
    }
}
