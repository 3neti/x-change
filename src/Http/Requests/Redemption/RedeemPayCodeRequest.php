<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Redemption;

use Illuminate\Foundation\Http\FormRequest;

class RedeemPayCodeRequest extends FormRequest
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
            'mobile' => ['required', 'string'],
            'recipient_country' => ['nullable', 'string', 'max:10'],
            'secret' => ['nullable'],
            'inputs' => ['present', 'array'],
            'bank_account' => ['required', 'array'],
            'bank_account.bank_code' => ['required', 'string'],
            'bank_account.account_number' => ['required', 'string'],
        ];
    }
}
