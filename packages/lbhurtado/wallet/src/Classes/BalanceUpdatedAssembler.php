<?php

namespace LBHurtado\Wallet\Classes;

use Bavix\Wallet\Internal\Assembler\BalanceUpdatedEventAssemblerInterface;
use Bavix\Wallet\Internal\Events\BalanceUpdatedEventInterface;
use LBHurtado\PaymentGateway\Events\BalanceUpdated;
use Bavix\Wallet\Models\Wallet;
use DateTimeImmutable;

class BalanceUpdatedAssembler implements BalanceUpdatedEventAssemblerInterface
{
    /**
     * @param Wallet $wallet
     * @return BalanceUpdatedEventInterface
     */
    public function create(Wallet $wallet): BalanceUpdatedEventInterface
    {
        return new BalanceUpdated($wallet, new DateTimeImmutable());
    }
}
