<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\PartnerApi;

use LBHurtado\XChange\Http\Requests\EstimatePayCodeRequest;

class EstimatePartnerPayCodeRequest extends EstimatePayCodeRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'issuer_id' => ['prohibited'],
            'metadata.issuer_id' => ['prohibited'],
            'metadata.issuer_email' => ['prohibited'],
            'metadata.issuer_mobile' => ['prohibited'],
        ];
    }
}
