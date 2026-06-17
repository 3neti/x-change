<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum ProviderProvisioningMode: string
{
    case LedgerWallet = 'ledger_wallet';
    case WalletCreate = 'wallet_create';
    case WalletResolve = 'wallet_resolve';
    case WalletUpgrade = 'wallet_upgrade';
    case BankAccountLink = 'bank_account_link';
    case Hybrid = 'hybrid';
}
