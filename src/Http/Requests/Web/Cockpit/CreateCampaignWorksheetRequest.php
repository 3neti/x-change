<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCampaignWorksheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'profile' => mb_strtolower(trim((string) $this->input('profile', 'payroll'))),
            'fulfillment_mode' => mb_strtolower(trim((string) $this->input(
                'fulfillment_mode',
                'pay_code_distribution',
            ))),
            'delivery_plan' => array_values(array_filter(
                (array) $this->input('delivery_plan', ['csv']),
                fn (mixed $value): bool => is_string($value) && trim($value) !== '',
            )),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:160'],
            'profile' => ['required', Rule::in(['payroll', 'assistance'])],
            'fulfillment_mode' => [
                'required',
                Rule::in(['pay_code_distribution', 'direct_bank_transfer']),
            ],
            'delivery_plan' => ['required', 'array', 'min:1', 'max:3'],
            'delivery_plan.*' => ['string', Rule::in(['csv', 'sms', 'email'])],
        ];
    }
}
