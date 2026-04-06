<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class OnboardIssuerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile' => ['required', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:10'],
            'identity' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
