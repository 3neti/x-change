<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use LBHurtado\Voucher\Data\InputFieldsData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Enums\VoucherInputField;
use Propaganistas\LaravelPhone\Rules\Phone;
use App\Rules\ValidISODuration;

class VoucherInstructionDataRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Make sure to authorize the request properly
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cash.amount'     => 'required|numeric|min:0',
            'cash.currency'   => 'required|string|size:3',

            'cash.validation.secret'   => 'nullable|string',
            'cash.validation.mobile'   => ['nullable', (new Phone)->country('PH')->type('mobile')],
            'cash.validation.country'  => 'nullable|string|size:2',
            'cash.validation.location' => 'nullable|string',
            'cash.validation.radius'   => 'nullable|string',

            'inputs' => ['nullable', 'array'],
            'inputs.fields' => ['nullable', 'array'],
            'inputs.fields.*' => ['nullable', 'string', 'in:' . implode(',', array_column(VoucherInputField::cases(), 'value'))],

            'feedback.mobile'   => ['nullable', (new Phone)->country('PH')->type('mobile')],
            'feedback.email'    => 'nullable|email',
            'feedback.webhook'  => 'nullable|url',

            'rider.message' => 'nullable|string|min:1',
            'rider.url'     => 'nullable|url',

            'count'  => 'required|integer|min:1',
            'prefix' => 'nullable|string|min:1',
            'mask'   => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if (!preg_match("/^[\*\-]+$/", $value)) {
                        $fail('The :attribute may only contain asterisks (*) and hyphens (-).');
                    }

                    $asterisks = substr_count($value, '*');

                    if ($asterisks < 4) {
                        $fail('The :attribute must contain at least 4 asterisks (*).');
                    }

                    if ($asterisks > 6) {
                        $fail('The :attribute must contain at most 6 asterisks (*).');
                    }
                },
            ],
            'ttl' => ['nullable', new ValidISODuration()],
            'starts_at'  => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
        ];
    }

    // in VoucherInstructionDataRequest
    public function toData(): VoucherInstructionsData
    {
        $validated = $this->validated();

        return VoucherInstructionsData::from([
            'cash' => [
                'amount' => $validated['cash']['amount'],
                'currency' => $validated['cash']['currency'],
                'validation' => [
                    'secret'   => $validated['cash']['validation']['secret'] ?? null,
                    'mobile'   => $validated['cash']['validation']['mobile'] ?? null,
                    'country'  => $validated['cash']['validation']['country'] ?? null,
                    'location' => $validated['cash']['validation']['location'] ?? null,
                    'radius'   => $validated['cash']['validation']['radius'] ?? null,
                ],
            ],
            'inputs' => [
                'fields' => $validated['inputs']['fields'] ?? null,
            ],
            'feedback' => [
                'email'   => $validated['feedback']['email'] ?? null,
                'mobile'  => $validated['feedback']['mobile'] ?? null,
                'webhook' => $validated['feedback']['webhook'] ?? null,
            ],
            'rider' => [
                'message' => $validated['rider']['message'] ?? '',
                'url'     => $validated['rider']['url'] ?? '',
            ],
            'count'      => $validated['count'],
            'prefix'     => $validated['prefix'] ?? '',
            'mask'       => $validated['mask'] ?? '',
            'ttl'        => $validated['ttl'] ?? null,
            'starts_at'  => $validated['starts_at'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
        ]);
    }
}
