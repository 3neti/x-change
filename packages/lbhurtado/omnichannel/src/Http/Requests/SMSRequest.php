<?php

namespace LBHurtado\OmniChannel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Propaganistas\LaravelPhone\Rules\Phone;

class SMSRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', (new Phone)->country('PH')->type('mobile')],
            'to' => ['required', 'string'],
            'message' => ['required', 'string'],
        ];
    }

    public function validated($key = null, $default = null)
    {
        $validated = parent::validated();

        // Convert 'from' to a dialing format
        $validated['from'] = phone($validated['from'], 'PH')->formatForMobileDialingInCountry('PH');

        return $validated;
    }
}

