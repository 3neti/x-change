<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvertCampaignWorksheetIntakeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'event_reference' => trim((string) $this->input('event_reference')),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:160'],
            'profile' => ['required', Rule::in(['payroll', 'assistance', 'gaming'])],
            'event_reference' => ['required_if:profile,gaming', 'nullable', 'string', 'max:191'],
            'fulfillment_mode' => ['required', Rule::in(['pay_code_distribution', 'direct_bank_transfer'])],
            'included_source_rows' => ['required', 'array', 'min:1', 'max:10000'],
            'included_source_rows.*' => ['integer', 'min:2', 'distinct'],
            'exclude_invalid_rows' => ['required', 'boolean'],
        ];
    }
}
