<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use LBHurtado\XChange\Contracts\CockpitTreasuryAccessContract;

class ApproveCockpitFundingReconciliationRequest extends FormRequest
{
    public function authorize(CockpitTreasuryAccessContract $access): bool
    {
        $actor = $this->user();

        return $actor !== null
            && $access->canManageTreasuryReconciliation($actor);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['prohibited'],
            'amount_minor' => ['prohibited'],
            'provider_observation_id' => ['prohibited'],
            'provider_transaction_id' => ['prohibited'],
        ];
    }
}
