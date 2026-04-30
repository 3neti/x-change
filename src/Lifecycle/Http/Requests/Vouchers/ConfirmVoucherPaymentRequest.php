<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Requests\Vouchers;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmVoucherPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', 'string', 'in:succeeded,failed,pending'],
            'provider' => ['nullable', 'string'],
            'provider_reference' => ['nullable', 'string'],
            'provider_transaction_id' => ['nullable', 'string'],
            'payer' => ['nullable', 'array'],
            'meta' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string'],
        ];
    }

    public function payload(): array
    {
        return array_merge($this->validated(), [
            'status' => $this->validated('status', 'succeeded'),
            'currency' => $this->validated('currency', 'PHP'),
        ]);
    }
}
