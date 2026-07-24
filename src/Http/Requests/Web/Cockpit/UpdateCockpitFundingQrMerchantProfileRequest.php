<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCockpitFundingQrMerchantProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'city' => trim((string) $this->input('city')),
            'merchant_category_code' => trim((string) $this->input('merchant_category_code')),
            'merchant_name_template' => trim((string) $this->input('merchant_name_template')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:25', 'not_regex:/[\x00-\x1F\x7F]/'],
            'city' => ['required', 'string', 'max:15', 'not_regex:/[\x00-\x1F\x7F]/'],
            'merchant_category_code' => ['required', 'regex:/^\d{4}$/'],
            'merchant_name_template' => [
                'required',
                'string',
                Rule::in([
                    '{name}',
                    '{name} - {city}',
                    '{app_name} - {name}',
                ]),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $rendered = str_replace(
                    ['{name}', '{city}', '{app_name}'],
                    [
                        (string) $this->input('name'),
                        (string) $this->input('city'),
                        (string) config('x-change.product.name', 'X-Change'),
                    ],
                    (string) $this->input('merchant_name_template'),
                );

                if (mb_strlen($rendered, 'UTF-8') > 25) {
                    $validator->errors()->add(
                        'merchant_name_template',
                        'The rendered QR merchant label must fit within 25 characters.',
                    );
                }
            },
        ];
    }
}
