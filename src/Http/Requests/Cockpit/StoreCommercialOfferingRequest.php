<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Cockpit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCommercialOfferingRequest extends FormRequest
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
        return [
            'profile' => ['required', Rule::in(['pay_code', 'account_funding'])],
            'effective_at' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.reference' => ['required', 'string', 'max:160', 'distinct'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'rules' => ['required', 'array', 'min:1'],
            'rules.*.reference' => ['required', 'string', 'max:160', 'distinct'],
            'rules.*.method' => ['required', Rule::in(['fixed', 'basis_points', 'residual'])],
            'rules.*.value' => ['nullable', 'numeric', 'min:0'],
            'rules.*.minimum_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'rules.*.maximum_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'rules.*.recipient_reference' => ['required', 'string', 'max:190'],
            'rules.*.participant_role' => ['nullable', 'string', 'max:100'],
        ];
    }
}
