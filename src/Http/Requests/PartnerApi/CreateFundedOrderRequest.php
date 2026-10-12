<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\PartnerApi;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LBHurtado\XChange\Http\Requests\GeneratePayCodeRequest;

final class CreateFundedOrderRequest extends GeneratePayCodeRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'cash.amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01'],
            'funding_method' => ['required', Rule::in(['qr_ph', 'bank_transfer'])],
            'payer_mobile' => ['required_if:funding_method,bank_transfer', 'nullable', 'string', 'max:32'],
            'issuer_id' => ['prohibited'],
            'metadata.issuer_id' => ['prohibited'],
            'metadata.issuer_email' => ['prohibited'],
            'metadata.issuer_mobile' => ['prohibited'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                $key = $this->header((string) config('x-change.api.idempotency.header', 'Idempotency-Key'));

                if (! is_string($key) || trim($key) === '' || mb_strlen($key) > 160) {
                    $validator->errors()->add('idempotency_key', 'A valid Idempotency-Key header is required.');
                }
            },
        ];
    }
}
