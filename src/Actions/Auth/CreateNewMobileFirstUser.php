<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use LBHurtado\XChange\Support\Auth\MobileNumber;
use Throwable;

class CreateNewMobileFirstUser implements CreatesNewUsers
{
    /**
     * @param  array<string, string|null>  $input
     */
    public function create(array $input): Authenticatable
    {
        $emailRequired = (bool) config('x-change.onboarding.email_required', false);
        $userModel = (string) config('auth.providers.users.model', 'App\\Models\\User');
        $mobile = MobileNumber::normalize($input['mobile'] ?? null);

        Validator::make([
            ...$input,
            'mobile' => $mobile,
        ], [
            'name' => ['nullable', 'string', 'max:255'],
            'mobile' => [
                'required',
                'string',
                'max:32',
                Rule::unique('users', 'mobile'),
            ],
            'email' => [$emailRequired ? 'required' : 'nullable', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:4', 'confirmed'],
        ])->validate();

        /** @var class-string<Model&Authenticatable> $userModel */
        $user = new $userModel;

        $user->forceFill([
            'name' => $input['name'] ?: $mobile,
            'mobile' => $mobile,
            'mobile_verified_at' => now(),
            'email' => $input['email'] ?: null,
            'password' => Hash::make((string) $input['password']),
        ]);

        $user->save();

        if (method_exists($user, 'setMobileChannel')) {
            try {
                $user->setMobileChannel($mobile);
            } catch (Throwable) {
                // The raw users.mobile column remains the Fortify identity source.
            }
        }

        return $user;
    }
}
