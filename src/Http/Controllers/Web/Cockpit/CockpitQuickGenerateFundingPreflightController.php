<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Enums\OnDemandIssuanceFundingBasis;
use LBHurtado\XChange\Http\Requests\GeneratePayCodeRequest;
use LBHurtado\XChange\Services\Banking\SelectedAccountClientFunds;
use LBHurtado\XChange\Services\Funding\OnDemandIssuanceFundingPolicy;
use LBHurtado\XChange\Services\Funding\OnDemandIssuanceFundingRequirement;

final class CockpitQuickGenerateFundingPreflightController extends Controller
{
    public function __invoke(
        GeneratePayCodeRequest $request,
        EstimatePayCodeCost $estimator,
        OnDemandIssuanceFundingPolicy $policy,
        OnDemandIssuanceFundingRequirement $requirements,
    ): JsonResponse {
        $issuer = $request->user();

        if ($issuer === null) {
            throw ValidationException::withMessages([
                'authorization' => ['Cockpit funding preflight requires an authenticated operator.'],
            ]);
        }

        $payload = $request->validated();
        $pricing = $estimator->handle($payload);
        $basis = $policy->enabled()
            ? $policy->basis()
            : OnDemandIssuanceFundingBasis::Shortfall;
        $requirement = $requirements->forBasis($issuer, $pricing, $basis);
        $selectedFunds = app(SelectedAccountClientFunds::class)->forOwner($issuer);
        $selectedBalance = $selectedFunds['amount_minor'];
        $selectedShortfall = $selectedBalance === null ? null : max(0, $requirement->requiredAmountMinor - max(0, $selectedBalance));
        $externalAmountMinor = $policy->enabled() ? $requirement->externalAmountMinor : 0;

        return response()->json([
            'schema' => 'x-change.cockpit.issuance-funding-preflight.v1',
            'status' => 'ready',
            'currency' => $pricing->currency,
            'pay_code_value' => $pricing->pay_code_value,
            'account_debit' => $pricing->account_debit,
            'pricing' => [
                'base_fee' => $pricing->base_fee,
                'total' => $pricing->total,
                'components' => $pricing->components,
                'charges' => $pricing->charges,
                'commercial_offering_reference' => $pricing->commercial_offering_reference,
                'commercial_offering_version' => $pricing->commercial_offering_version,
                'commercial_offering_snapshot_hash' => $pricing->commercial_offering_snapshot_hash,
                'commercial_quote_reference' => $pricing->commercial_quote_reference,
            ],
            'funding' => [
                'policy_enabled' => $policy->enabled(),
                'basis' => $basis->value,
                'available_client_funds_minor' => $selectedBalance,
                'account_shortfall_minor' => $selectedShortfall,
                'required_amount_minor' => $requirement->requiredAmountMinor,
                'reserved_client_funds_minor' => $requirement->reservedClientFundsMinor,
                'external_amount_minor' => $externalAmountMinor,
                'execution' => $externalAmountMinor > 0
                    ? 'payment_required'
                    : 'direct',
                'reason' => $externalAmountMinor > 0
                    ? 'external_funding_required'
                    : ($selectedBalance === null
                        ? 'selected_account_balance_unavailable'
                        : ($selectedShortfall > 0 ? 'selected_account_shortfall' : 'client_funds_cover_required_debit')),
            ],
            'expires_at' => now()->addSeconds(30)->toIso8601String(),
        ]);
    }
}
