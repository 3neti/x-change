<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Data\Banking\SelectedBankingAccount;

interface BankingAccountSelectorContract
{
    public function select(Model $owner, string $provider, string $capability, ?int $accountId = null): SelectedBankingAccount;
}
