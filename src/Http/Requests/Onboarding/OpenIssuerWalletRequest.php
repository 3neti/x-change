<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class OpenIssuerWalletRequest extends FormRequest
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
            'issuer_id' => ['required'],
            'wallet' => ['required', 'array'],
            'wallet.slug' => ['required', 'string', 'max:100'],
            'wallet.name' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
