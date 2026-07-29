<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateCampaignWorksheetRowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'mobile' => trim((string) $this->input('mobile')),
            'bank_account' => trim((string) $this->input('bank_account')),
            'bank_code' => trim((string) $this->input('bank_code')),
            'email' => trim((string) $this->input('email')),
            'remarks' => trim((string) $this->input('remarks')),
            'external_reference' => trim((string) $this->input('external_reference')),
            'delivery_preference' => mb_strtolower(trim((string) $this->input('delivery_preference', 'manual'))),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount_minor' => ['required', 'integer', 'min:1', 'max:999999999999999'],
            'name' => ['nullable', 'string', 'max:160'],
            'mobile' => ['nullable', 'string', 'max:32', 'required_without:bank_account'],
            'bank_account' => ['nullable', 'string', 'max:100', 'required_without:mobile'],
            'bank_code' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email:rfc', 'max:191'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'external_reference' => ['nullable', 'string', 'max:191'],
            'delivery_preference' => ['required', 'in:manual,csv,sms,email'],
        ];
    }
}
