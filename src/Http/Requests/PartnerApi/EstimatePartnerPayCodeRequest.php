<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\PartnerApi;

use LBHurtado\XChange\Http\Requests\EstimatePayCodeRequest;

class EstimatePartnerPayCodeRequest extends EstimatePayCodeRequest
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
            'issuer_id' => ['prohibited'],
            'metadata.issuer_id' => ['prohibited'],
            'metadata.issuer_email' => ['prohibited'],
            'metadata.issuer_mobile' => ['prohibited'],
        ];
    }
}
