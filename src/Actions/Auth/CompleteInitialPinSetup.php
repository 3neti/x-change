<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Auth;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LBHurtado\XChange\Services\Onboarding\AccountPinSetupState;
use RuntimeException;

final readonly class CompleteInitialPinSetup
{
    public function __construct(
        private AccountPinSetupState $pinSetup,
    ) {}

    public function handle(Model $user, string $pin): Model
    {
        return DB::transaction(function () use ($pin, $user): Model {
            $lockedUser = $user::query()
                ->lockForUpdate()
                ->find($user->getKey());

            if (! $lockedUser instanceof Model) {
                throw new RuntimeException('The authenticated Account is unavailable.');
            }

            if (! $this->pinSetup->isRequired($lockedUser)) {
                throw new AuthorizationException(
                    'Initial PIN setup is not required for this Account.',
                );
            }

            $lockedUser->setAttribute('password', Hash::make($pin));
            $this->pinSetup->markCompleted($lockedUser, save: false);
            $lockedUser->save();

            return $lockedUser;
        });
    }
}
