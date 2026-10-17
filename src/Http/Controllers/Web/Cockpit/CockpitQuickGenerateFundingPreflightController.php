<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Enums\OnDemandIssuanceFundingBasis;
use LBHurtado\XChange\Http\Requests\GeneratePayCodeRequest;
use LBHurtado\XChange\Services\BuildBalanceOverview;
use LBHurtado\XChange\Services\Funding\OnDemandIssuanceFundingPolicy;
use LBHurtado\XChange\Services\Funding\OnDemandIssuanceFundingRequirement;

final class CockpitQuickGenerateFundingPreflightController extends Controller
{
    public function __invoke(
        GeneratePayCodeRequest $request,
        EstimatePayCodeCost $estimator,
        OnDemandIssuanceFundingPolicy $policy,
        OnDemandIssuanceFundingRequirement $requirements,
        BuildBalanceOverview $balances,
    ): JsonResponse {
        $issuer = $request->user();

        if ($issuer === null) {
            throw ValidationException::withMessages([
                'authorization' => ['Cockpit funding preflight requires an authenticated operator.'],
            ]);
        }

        $payload = $request->validated();
        $pricing = $estimator->handle($payload);
        $overview = $balances->handle($issuer, syncIfStale: false);
        $localBalance = collect((array) data_get($overview, 'balances', []))->firstWhere('key', 'local_ledger');
        $availableClientFundsMinor = is_array($localBalance) && is_numeric($localBalance['balance_minor'] ?? null)
            ? (int) $localBalance['balance_minor']
            : null;
        $basis = $policy->enabled()
            ? $policy->basis()
            : OnDemandIssuanceFundingBasis::Shortfall;
        $requirement = $requirements->forBasis($issuer, $pricing, $basis);
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
                'available_client_funds_minor' => $availableClientFundsMinor,
                'required_amount_minor' => $requirement->requiredAmountMinor,
                'reserved_client_funds_minor' => $requirement->reservedClientFundsMinor,
                'external_amount_minor' => $externalAmountMinor,
                'execution' => $externalAmountMinor > 0
                    ? 'payment_required'
                    : 'direct',
                'reason' => $externalAmountMinor > 0
                    ? 'external_funding_required'
                    : 'client_funds_cover_required_debit',
            ],
            'expires_at' => now()->addSeconds(30)->toIso8601String(),
        ]);
    }
}
