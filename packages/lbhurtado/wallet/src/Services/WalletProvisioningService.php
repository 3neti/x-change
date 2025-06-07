<?php

namespace LBHurtado\Wallet\Services;

use LBHurtado\Wallet\Tests\Models\User; //TODO: change this to dynamic
use LBHurtado\Wallet\Enums\WalletType;

class WalletProvisioningService
{
    public function createDefaultWalletsForUser(User $user): void
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
