<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCampaignWorksheetIntakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'profile' => ['required', Rule::in(['payroll', 'assistance'])],
            'fulfillment_mode' => ['required', Rule::in(['pay_code_distribution', 'direct_bank_transfer'])],
            'mapping' => ['required', 'array'],
            'mapping.*' => ['nullable', 'string', 'max:255'],
            'default_wallet' => ['required', 'string', 'max:80'],
            'default_delivery_preference' => ['required', Rule::in(['manual', 'sms', 'email'])],
        ];
    }
}
