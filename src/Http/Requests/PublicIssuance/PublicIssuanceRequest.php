<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\PublicIssuance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PublicIssuanceRequest extends FormRequest
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
        $minimum = max(1, (int) config('x-change.public_auto_generate.minimum_principal_minor', 100));
        $maximum = max($minimum, (int) config('x-change.public_auto_generate.maximum_principal_minor', 100_000));

        return [
            'amount_minor' => ['required', 'integer', 'min:'.$minimum, 'max:'.$maximum],
            'currency' => [
                'required',
                'string',
                Rule::in((array) config('x-change.public_auto_generate.currencies', ['PHP'])),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => strtoupper(trim((string) $this->input('currency', 'PHP'))),
        ]);
    }
}
