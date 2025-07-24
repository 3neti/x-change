<?php

namespace App\Http\Requests;

use LBHurtado\Voucher\Data\VoucherInstructionsData;
use Illuminate\Foundation\Http\FormRequest;
use Spatie\LaravelData\WithData;

class VoucherInstructionDataRequest extends FormRequest
{
    use WithData;

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
        return VoucherInstructionsData::rules();
    }

    protected function dataClass(): string
    {
        return VoucherInstructionsData::class;
    }
}
