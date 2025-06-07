<?php

namespace LBHurtado\Wallet\Enums;

enum WalletType: string
{
    case EXCHANGE = 'x-change';
    case REWARDS = 'rewards';
    case ESCROW = 'escrow';
    case COMMISSION = 'commission';

    public function label(): string
    {
        return match ($this) {
            self::EXCHANGE => 'X-Change Wallet',
            self::REWARDS => 'Rewards Wallet',
            self::ESCROW => 'Escrow Wallet',
            self::COMMISSION => 'Commission Wallet',
        };
    }

    public function defaultMeta(): array
    {
        return match ($this) {
            self::EXCHANGE => ['description' => 'Main wallet for platform transactions.'],
            self::REWARDS => ['description' => 'Wallet for loyalty points and rewards.'],
            self::ESCROW => ['description' => 'Wallet for held funds.'],
            self::COMMISSION => ['description' => 'Platform earnings.'],
        };
    }
}
