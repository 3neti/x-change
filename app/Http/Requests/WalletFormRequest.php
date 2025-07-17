<?php

namespace App\Http\Requests;

use App\Validators\VoucherRedemptionValidator;
use Illuminate\Foundation\Http\FormRequest;
use Propaganistas\LaravelPhone\Rules\Phone;
use Illuminate\Validation\Validator;

/**
 * WalletFormRequest handles validation for wallet redemption inputs.
 *
 * It includes:
 * - Format validation for mobile number (Philippines, mobile-only)
 * - Optional fields for bank_code and account_number
 * - Optional secret field
 *
 * Cross-validations performed:
 * - Ensures the provided mobile number matches the voucher's configured recipient
 * - Verifies the secret (if provided) against the hashed voucher secret
 */
class WalletFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Define the base validation rules for the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mobile'         => ['required', (new Phone)->country('PH')->type('mobile')],
            'country'        => ['required', 'string'],
            'bank_code'      => ['nullable', 'string'],
            'account_number' => ['nullable', 'string'],
            'secret'         => ['nullable', 'string'],
        ];
    }

    /**
     * Register additional validator logic for cross-field and voucher-based checks.
     *
     * @param \Illuminate\Validation\Validator $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function (Validator $v) {
            $voucher = $this->route('voucher');
            $validator = new VoucherRedemptionValidator($voucher, $v->errors());

            $validator->validateMobile($this->input('mobile'));
            $validator->validateSecret($this->input('secret'));
        });
    }
}
