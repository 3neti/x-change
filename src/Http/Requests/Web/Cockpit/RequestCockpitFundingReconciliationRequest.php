<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LBHurtado\XChange\Actions\Funding\AssessPaidOnDemandIssuanceRecovery;
use LBHurtado\XChange\Contracts\CockpitTreasuryAccessContract;
use LBHurtado\XChange\Enums\FundingReconciliationAction;
use LBHurtado\XChange\Models\FundingSuspenseCase;

class RequestCockpitFundingReconciliationRequest extends FormRequest
{
    public function authorize(
        CockpitTreasuryAccessContract $access,
        AssessPaidOnDemandIssuanceRecovery $recovery,
    ): bool {
        $actor = $this->user();

        if ($actor === null) {
            return false;
        }

        $case = $this->route('case');
        $intent = $case instanceof FundingSuspenseCase ? $case->fundingIntent : null;

        if ($this->input('action') === FundingReconciliationAction::RecoverPaidOnDemandIssuance->value) {
            return $intent !== null
                && $intent->created_by_type === $actor::class
                && (string) $intent->created_by_id === (string) $actor->getAuthIdentifier()
                && $recovery->eligible($case);
        }

        return $access->canManageTreasuryReconciliation($actor);
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
