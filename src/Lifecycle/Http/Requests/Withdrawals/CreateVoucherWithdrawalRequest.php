<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Requests\Withdrawals;

use Illuminate\Foundation\Http\FormRequest;

class CreateVoucherWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'voucher_code' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'max:10'],
            'bank_account' => ['required', 'array'],
            'bank_account.bank_code' => ['required', 'string'],
            'bank_account.account_number' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
