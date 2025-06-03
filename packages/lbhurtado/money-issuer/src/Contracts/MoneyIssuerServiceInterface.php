<?php

namespace LBHurtado\MoneyIssuer\Contracts;

use LBHurtado\MoneyIssuer\Data\{TransferData};
use LBHurtado\MoneyIssuer\Data\BalanceData;

interface MoneyIssuerServiceInterface
{
    public function checkBalance(string $account): BalanceData;

    public function deposit(string $account, float $amount, string $currency = 'PHP', array $meta = []): bool;

    public function withdraw(string $account, float $amount, string $currency = 'PHP', array $meta = []): bool;

    public function transfer(string $from, string $to, float $amount, string $currency = 'PHP', array $meta = []): TransferData;
}
