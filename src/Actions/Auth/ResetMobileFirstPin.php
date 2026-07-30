<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Auth;

use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use LBHurtado\XChange\Services\Onboarding\AccountPinSetupState;
use RuntimeException;

final readonly class ResetMobileFirstPin
{
    public function __construct(
        private AccountPinSetupState $pinSetup,
    ) {}

    /**
     * @param  array<string, string>  $input
     */
    public function reset(CanResetPassword $user, array $input): void
    {
        Validator::make($input, [
            'password' => [
                'required',
                'string',
                'confirmed',
                'regex:/^\d+$/',
                'min:4',
                'max:12',
            ],
        ], [
            'password.regex' => 'Your PIN must contain numbers only.',
            'password.min' => 'Your PIN must contain at least 4 digits.',
            'password.max' => 'Your PIN may contain at most 12 digits.',
        ])->validate();

        if (! $user instanceof Model) {
            throw new RuntimeException(
                'The configured password broker Account must be an Eloquent model.',
            );
        }

        $user->forceFill([
            'password' => Hash::make((string) $input['password']),
        ]);

        if ($this->pinSetup->isRequired($user)) {
            $this->pinSetup->markCompleted($user, save: false);
        }

        $user->save();
    }
}
