<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EstimatePayCodeRequest extends FormRequest
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
            'cash' => ['required', 'array'],
            'cash.amount' => ['required', 'numeric', 'min:0.01'],
            'cash.currency' => ['required', 'string', 'max:10'],
            'cash.settlement_rail' => ['nullable', 'string', 'max:50'],

            'cash.validation' => ['nullable', 'array'],
            'cash.validation.secret' => ['nullable'],
            'cash.validation.mobile' => ['nullable', 'string'],
            'cash.validation.payable' => ['nullable', 'string'],
            'cash.validation.country' => ['nullable', 'string', 'max:10'],
            'cash.validation.location' => ['nullable', 'string'],
            'cash.validation.radius' => ['nullable', 'string'],

            'inputs' => ['required', 'array'],
            'inputs.fields' => ['required', 'array'],

            'feedback' => ['required', 'array'],
            'feedback.email' => ['nullable', 'email'],
            'feedback.mobile' => ['nullable', 'string'],
            'feedback.webhook' => ['nullable', 'url'],

            'rider' => ['required', 'array'],
            'rider.message' => ['nullable'],
            'rider.url' => ['nullable', 'url'],
            'rider.redirect_timeout' => ['nullable'],
            'rider.splash' => ['nullable'],
            'rider.splash_timeout' => ['nullable'],
            'rider.og_source' => ['nullable'],

            'count' => ['nullable', 'integer', 'min:1'],
            'prefix' => ['nullable', 'string'],
            'mask' => ['nullable', 'string'],
            'ttl' => ['nullable'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
