<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Banking;

final readonly class SelectedBankingAccount
{
    public function __construct(
        public string $provider,
        public string $mode,
        public ?int $accountId,
    ) {}
}
