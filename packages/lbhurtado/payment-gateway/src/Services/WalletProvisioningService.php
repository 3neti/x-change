<?php

namespace LBHurtado\PaymentGateway\Services;

use LBHurtado\PaymentGateway\Tests\Models\User;
use LBHurtado\PaymentGateway\Enums\WalletType;

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
