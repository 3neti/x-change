<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Wallet;

use Spatie\LaravelData\Data;

class GetWalletBalanceResultData extends Data
{
    public function __construct(
        public mixed $issuer_id,
        public mixed $wallet_id,
        public ?string $wallet_slug,
        public ?string $wallet_name,
        public int|float|string|null $balance,
        public string $currency,
    ) {}
}
