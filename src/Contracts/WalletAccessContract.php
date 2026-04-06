<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface WalletAccessContract
{
    public function resolveForUser(mixed $user): mixed;

    public function getBalance(mixed $wallet): int|float|string;

    public function assertCanAfford(mixed $wallet, int|float|string $amount): void;

    /**
     * @param  array<string, mixed>  $meta
     */
    public function debit(mixed $wallet, int|float|string $amount, array $meta = []): mixed;
}
