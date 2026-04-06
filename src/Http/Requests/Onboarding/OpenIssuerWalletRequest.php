<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class OpenIssuerWalletRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'issuer_id' => ['required', 'integer'],

            'wallet' => ['required', 'array'],
            'wallet.slug' => ['required', 'string'],
            'wallet.name' => ['required', 'string'],

            'metadata' => ['nullable', 'array'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
