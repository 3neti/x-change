<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum FundingRequestType: string
{
    case BankTransfer = 'bank_transfer';
    case CashHandover = 'cash_handover';
    case PreciousMetal = 'precious_metal';
    case Jewelry = 'jewelry';
    case Vehicle = 'vehicle';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'Bank transfer',
            self::CashHandover => 'Cash handover',
            self::PreciousMetal => 'Gold or precious metal',
            self::Jewelry => 'Jewelry',
            self::Vehicle => 'Vehicle',
            self::Other => 'Other approved asset',
        };
    }
}
