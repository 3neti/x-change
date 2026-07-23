<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LBHurtado\XChange\Enums\FundingReconciliationAction;

class RequestCockpitFundingReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'action' => strtolower(trim((string) $this->input('action'))),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::enum(FundingReconciliationAction::class)],
            'amount' => ['prohibited'],
            'amount_minor' => ['prohibited'],
            'provider_observation_id' => ['prohibited'],
            'provider_transaction_id' => ['prohibited'],
        ];
    }
}
