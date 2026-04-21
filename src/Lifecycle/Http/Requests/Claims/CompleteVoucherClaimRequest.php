<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Requests\Claims;

use Illuminate\Foundation\Http\FormRequest;

class CompleteVoucherClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reference_id' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
