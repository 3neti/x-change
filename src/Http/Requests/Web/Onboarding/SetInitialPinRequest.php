<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Onboarding;

use Illuminate\Foundation\Http\FormRequest;
use LBHurtado\XChange\Services\Onboarding\AccountPinSetupState;

final class SetInitialPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && app(AccountPinSetupState::class)->isRequired($user);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'password' => [
                'required',
                'string',
                'confirmed',
                'regex:/^\d+$/',
                'min:4',
                'max:12',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.regex' => 'Your PIN must contain numbers only.',
            'password.min' => 'Your PIN must contain at least 4 digits.',
            'password.max' => 'Your PIN may contain at most 12 digits.',
        ];
    }
}
