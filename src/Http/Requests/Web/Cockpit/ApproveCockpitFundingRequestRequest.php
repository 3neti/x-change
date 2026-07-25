<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Foundation\Http\FormRequest;
use LBHurtado\XChange\Services\Funding\FundingRequestAccess;

class ApproveCockpitFundingRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor !== null && app(FundingRequestAccess::class)->isReviewer($actor);
    }

    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'recognized_value_minor' => ['prohibited'],
            'amount_minor' => ['prohibited'],
            'currency' => ['prohibited'],
            'connection_reference' => ['prohibited'],
            'evidence_reference' => ['prohibited'],
            'provider_transaction_id' => ['prohibited'],
            'provider_payload' => ['prohibited'],
        ];
    }
}
