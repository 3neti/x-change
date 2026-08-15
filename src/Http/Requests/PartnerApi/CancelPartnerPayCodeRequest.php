<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\PartnerApi;

use Illuminate\Foundation\Http\FormRequest;

class CancelPartnerPayCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
            '_partner' => ['required', 'array:idempotency_key,correlation_id'],
            '_partner.idempotency_key' => ['required', 'string', 'max:160'],
            '_partner.correlation_id' => ['nullable', 'string', 'max:160'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            '_partner' => [
                'idempotency_key' => $this->header((string) config('x-change.api.idempotency.header', 'Idempotency-Key')),
                'correlation_id' => $this->header((string) config('x-change.api.correlation.header', 'X-Correlation-ID')),
            ],
        ]);
    }
}
