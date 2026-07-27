<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Cockpit;

use Illuminate\Foundation\Http\FormRequest;

class StorePayCodeTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:240'],
            'base_template_key' => ['required', 'string', 'max:64'],
            'instructions' => ['required', 'array'],
            'include_amount' => ['required', 'boolean'],
            'include_purpose' => ['required', 'boolean'],
        ];
    }
}
