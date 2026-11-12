<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\PartnerApi;

use LBHurtado\XChange\Http\Requests\GeneratePayCodeRequest;

class IssuePartnerPayCodeRequest extends GeneratePayCodeRequest
{
    public function rules(): array
    {
        $collectForSettlement = $this->input('voucher_type') === 'settlement'
            && $this->input('execution.schema') === 'voucher.execution.v2'
            && $this->input('execution.metadata.settlement_workflow') === 'collect_for_settlement';

        return [
            ...parent::rules(),
            ...($collectForSettlement ? ['cash.amount' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:0']] : []),
            'target_amount' => [$collectForSettlement ? 'required' : 'nullable', 'numeric', 'decimal:0,2', $collectForSettlement ? 'gt:0' : 'min:0'],
            'external_reference' => ['required', 'string', 'max:190', 'regex:/^[\w.:-]+$/'],
            'issuer_id' => ['prohibited'],
            'metadata.issuer_id' => ['prohibited'],
            'metadata.issuer_email' => ['prohibited'],
            'metadata.issuer_mobile' => ['prohibited'],
            '_partner' => ['required', 'array:idempotency_key,correlation_id'],
            '_partner.idempotency_key' => ['required', 'string', 'max:160'],
            '_partner.correlation_id' => ['nullable', 'string', 'max:160'],
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            '_partner' => [
                'idempotency_key' => $this->header((string) config('x-change.api.idempotency.header', 'Idempotency-Key')),
                'correlation_id' => $this->header((string) config('x-change.api.correlation.header', 'X-Correlation-ID')),
            ],
        ]);
    }
}
