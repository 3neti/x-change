<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Web\Cockpit;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use LBHurtado\XChange\Actions\Funding\AssessPaidOnDemandIssuanceRecovery;
use LBHurtado\XChange\Contracts\CockpitTreasuryAccessContract;
use LBHurtado\XChange\Enums\FundingReconciliationAction;
use LBHurtado\XChange\Models\FundingReconciliationRequest;

class ApproveCockpitFundingReconciliationRequest extends FormRequest
{
    public function authorize(
        CockpitTreasuryAccessContract $access,
        AssessPaidOnDemandIssuanceRecovery $recovery,
    ): bool {
        $actor = $this->user();

        if ($actor === null) {
            return false;
        }

        $request = $this->route('reconciliationRequest');

        if ($request instanceof FundingReconciliationRequest
            && $request->action === FundingReconciliationAction::RecoverPaidOnDemandIssuance) {
            $case = $request->suspenseCase;
            $order = $recovery->order($case);

            return $order?->issuer_type === $actor::class
                && (string) $order->issuer_id === (string) $actor->getAuthIdentifier()
                && $recovery->eligible($case);
        }

        return $access->canManageTreasuryReconciliation($actor);
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
