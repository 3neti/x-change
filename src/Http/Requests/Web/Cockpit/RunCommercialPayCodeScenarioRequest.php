<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RunCommercialPayCodeScenarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['amount' => 50]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'phase' => ['required', Rule::in(['prepare', 'approve', 'cancel'])],
            'checker' => ['required', 'integer', 'min:1'],
            'run_reference' => [
                Rule::requiredIf(in_array($this->string('phase')->toString(), ['approve', 'cancel'], true)),
                'nullable',
                'string',
                'max:80',
            ],
            'amount' => ['required', 'numeric', 'in:50'],
        ];
    }
}
