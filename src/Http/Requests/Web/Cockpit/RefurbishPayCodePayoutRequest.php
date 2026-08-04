<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class RefurbishPayCodePayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'bank_code' => mb_strtoupper(trim((string) $this->input('bank_code'))),
            'account_number' => trim((string) $this->input('account_number')),
            'mobile' => trim((string) $this->input('mobile')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bank_code' => ['required', 'string', 'max:64'],
            'account_number' => ['required', 'string', 'max:32'],
            'mobile' => ['nullable', 'string', 'max:32'],
        ];
    }
}
