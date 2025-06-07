<?php

namespace LBHurtado\Wallet\Services;

use LBHurtado\Wallet\Tests\Models\User; //TODO: change this to dynamic
use LBHurtado\Wallet\Enums\WalletType;
use Bavix\Wallet\Interfaces\Wallet;

class WalletProvisioningService
{
    public function createDefaultWalletsForUser(Wallet $user): void
    {
        foreach (WalletType::cases() as $type) {
            $user->getOrCreateWalletByType(
                $type->value,
                [
                    'name' => $type->label(),
                    'meta' => $type->defaultMeta(),
                ]
            );
        }
    }
}
