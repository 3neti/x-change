<?php

namespace App\Observers;

use LBHurtado\Wallet\Services\WalletProvisioningService;
use App\Enums\ChildType;
use App\Models\User;

class UserObserver
{
    public function creating(User $user): void
    {
        if ($this->isSystemUser($user)) {
            $user->type = ChildType::SYSTEM->value;
        }
    }

    public function created(User $user): void
    {
        $walletService = app(WalletProvisioningService::class);
        $walletService->createDefaultWalletsForUser($user);
    }

    public function updated(User $user): void
    {
        $column = config('account.system_user.identifier_column');

        if ($user->isDirty($column)) {
            if ($this->isSystemUser($user)) {
                $user->type = ChildType::SYSTEM->value;
            } else {
                $user->type = null;
            }

            $user->saveQuietly(); // avoid recursive observer call
        }
    }

    protected function isSystemUser(User $user): bool
    {
        $column = config('account.system_user.identifier_column');
        $identifier = config('account.system_user.identifier');

        return $user->$column == $identifier;
    }
}
