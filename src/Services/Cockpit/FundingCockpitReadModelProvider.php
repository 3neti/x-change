<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use LBHurtado\Wallet\Treasury\Models\TreasuryInventory;
use LBHurtado\XChange\Data\Cockpit\CockpitFundingReadModelData;
use LBHurtado\XChange\Models\FundingAccountHold;
use LBHurtado\XChange\Models\FundingDestinationPreference;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\FundingReconciliationRequest;
use LBHurtado\XChange\Models\FundingRecovery;
use LBHurtado\XChange\Models\FundingSettlement;
use LBHurtado\XChange\Models\FundingSuspenseCase;

class FundingCockpitReadModelProvider
{
    public function forOperator(Authenticatable $operator): CockpitFundingReadModelData
    {
        $actorType = $operator::class;
        $actorId = (string) $operator->getAuthIdentifier();
        $intentsQuery = FundingIntent::query()
            ->where('created_by_type', $actorType)
            ->where('created_by_id', $actorId);
        $intentIds = (clone $intentsQuery)->pluck('id');
        $settlements = FundingSettlement::query()
            ->whereIn('funding_intent_id', $intentIds)
            ->latest('settled_at')
            ->get();
        $openSuspenseCases = FundingSuspenseCase::query()
            ->whereIn('funding_intent_id', $intentIds)
            ->whereIn('status', ['open', 'monitoring'])
            ->with(['fundingIntent', 'reconciliationRequests'])
            ->latest('opened_at')
            ->get();
        $activeRecoveries = FundingRecovery::query()
            ->whereIn('funding_intent_id', $intentIds)
            ->where('outstanding_amount_minor', '>', 0)
            ->latest('opened_at')
            ->get();

        return new CockpitFundingReadModelData(
            summary: $this->summary($intentsQuery, $settlements, $openSuspenseCases, $activeRecoveries),
            providers: $this->providers($actorType, $actorId),
            intents: $this->intents($intentsQuery),
            suspense_cases: $this->suspenseCases($openSuspenseCases),
            approval_queue: $this->approvalQueue($actorType, $actorId),
            recovery_holds: $this->recoveryHolds($activeRecoveries),
            treasury_positions: $this->treasuryPositions($settlements),
            controls: [
                'funding_intent_required' => true,
                'manual_balance_adjustment_enabled' => false,
                'webhook_direct_credit_enabled' => false,
                'authoritative_provider_verification_required' => true,
                'dual_control_reconciliation_required' => true,
                'live_provider_balance_connected' => (bool) config('x-change.cockpit.header_provider_balance.enabled', true),
            ],
            redactions: [
                'payloads' => 'funding-operations-summary-only',
                'funding_addresses_exposed' => false,
                'provider_transaction_ids_exposed' => false,
                'provider_request_ids_exposed' => false,
                'account_references_exposed' => false,
                'webhook_payloads_exposed' => false,
                'raw_evidence_exposed' => false,
                'secrets_exposed' => false,
            ],
        );
    }

    /**
     * @param  Builder<FundingIntent>  $intentsQuery
     * @param  Collection<int, FundingSettlement>  $settlements
     * @param  Collection<int, FundingSuspenseCase>  $suspenseCases
     * @param  Collection<int, FundingRecovery>  $recoveries
     * @return array<string, int|string>
     */
    private function summary(
        Builder $intentsQuery,
        Collection $settlements,
        Collection $suspenseCases,
        Collection $recoveries,
    ): array {
        $currency = $this->currency($settlements->first()?->currency);
        $settledMinor = (int) $settlements->sum('net_amount_minor');
        $recoveryMinor = (int) $recoveries->sum('outstanding_amount_minor');

        return [
            'awaiting_funds' => (clone $intentsQuery)->where('status', 'awaiting_funds')->count(),
            'settled_funding' => $this->formatMoney($settledMinor, $currency),
            'open_suspense' => $suspenseCases->count(),
            'recovery_outstanding' => $this->formatMoney($recoveryMinor, $currency),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function providers(string $actorType, string $actorId): array
    {
        return collect((array) config('x-change.funding.providers', []))
            ->filter(fn (mixed $provider): bool => is_array($provider) && ($provider['enabled'] ?? false) === true)
            ->map(function (array $provider, string $code) use ($actorType, $actorId): array {
                $preference = FundingDestinationPreference::query()
                    ->with('providerAccountLink')
                    ->where('owner_type', $actorType)
                    ->where('owner_id', $actorId)
                    ->where('provider_code', $code)
                    ->first();
                $mode = $preference?->mode ?? 'shared';
                $link = $preference?->providerAccountLink;
                $dedicatedReady = $mode !== 'dedicated' || (
                    $link?->isReady() === true
                    && ($code === 'netbank'
                        ? in_array($link->verification_status, ['verified', 'credential_supplied'], true)
                        : $link->verification_status === 'ownership_verified')
                );

                return [
                    'code' => $code,
                    'label' => match ($code) {
                        'netbank' => 'NetBank',
                        'paynamics', 'paynamics_constellation' => 'Paynamics',
                        default => str($code)->headline()->toString(),
                    },
                    'status' => $dedicatedReady ? 'available' : 'blocked',
                    'authoritative_verification' => true,
                    'destination_mode' => $mode,
                    'destination_status' => $mode === 'shared'
                        ? 'platform_managed'
                        : ($link?->verification_status ?? 'not_configured'),
                    'destination_reference' => $mode === 'dedicated'
                        ? $link?->display_reference
                        : 'Platform-managed',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Builder<FundingIntent>  $query
     * @return array<int, array<string, mixed>>
     */
    private function intents(Builder $query): array
    {
        return (clone $query)
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(fn (FundingIntent $intent): array => [
                'reference' => $intent->reference,
                'provider' => $intent->provider_code,
                'amount' => $this->formatMoney($intent->expected_amount_minor, $intent->currency),
                'currency' => $intent->currency,
                'status' => $intent->status->value,
                'created_at' => $intent->created_at?->toIso8601String(),
                'expires_at' => $intent->expires_at?->toIso8601String(),
                'settled_at' => $intent->settled_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @param  Collection<int, FundingSuspenseCase>  $cases
     * @return array<int, array<string, mixed>>
     */
    private function suspenseCases(Collection $cases): array
    {
        return $cases
            ->take(20)
            ->map(function (FundingSuspenseCase $case): array {
                $pendingRequest = $case->reconciliationRequests
                    ->firstWhere('status', 'pending_approval');

                return [
                    'reference' => $case->reference,
                    'provider' => $case->provider_code,
                    'reason' => $case->reason_code,
                    'status' => $case->status,
                    'opened_at' => $case->opened_at?->toIso8601String(),
                    'pending_approval' => $pendingRequest !== null,
                    'pending_action' => $pendingRequest?->action->value,
                    'allowed_actions' => $pendingRequest === null
                        ? $this->allowedReconciliationActions($case)
                        : [],
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function approvalQueue(string $actorType, string $actorId): array
    {
        return FundingReconciliationRequest::query()
            ->where('status', 'pending_approval')
            ->with('suspenseCase')
            ->oldest('requested_at')
            ->limit(50)
            ->get()
            ->map(function (FundingReconciliationRequest $request) use ($actorType, $actorId): array {
                $requestedBySelf = $request->requested_by_type === $actorType
                    && $request->requested_by_id === $actorId;

                return [
                    'reference' => $request->reference,
                    'case_reference' => $request->suspenseCase->reference,
                    'provider' => $request->suspenseCase->provider_code,
                    'reason' => $request->suspenseCase->reason_code,
                    'action' => $request->action->value,
                    'status' => $request->status,
                    'requested_at' => $request->requested_at?->toIso8601String(),
                    'requested_by_self' => $requestedBySelf,
                    'can_approve' => ! $requestedBySelf,
                    'amount_input_allowed' => false,
                    'evidence_input_allowed' => false,
                ];
            })
            ->all();
    }

    /**
     * @return list<string>
     */
    private function allowedReconciliationActions(FundingSuspenseCase $case): array
    {
        $actions = [];

        if ($case->fundingIntent?->status?->value === 'suspense' && $case->webhook_receipt_id !== null) {
            $actions[] = 'retry_verification';
        }

        if ($case->fundingIntent?->status?->value === 'suspense' && $case->provider_funding_observation_id !== null) {
            $actions[] = 'match_verified_observation';
        }

        if ($case->fundingIntent?->status?->value === 'verified') {
            $actions[] = 'compensate_verified_posting';
        }

        return $actions;
    }

    /**
     * @param  Collection<int, FundingRecovery>  $recoveries
     * @return array<int, array<string, mixed>>
     */
    private function recoveryHolds(Collection $recoveries): array
    {
        $holdStatusByRecovery = FundingAccountHold::query()
            ->whereIn('funding_recovery_id', $recoveries->pluck('id'))
            ->pluck('status', 'funding_recovery_id');

        return $recoveries
            ->take(20)
            ->map(fn (FundingRecovery $recovery): array => [
                'reference' => $recovery->reference,
                'status' => $recovery->status,
                'hold_status' => $holdStatusByRecovery->get($recovery->getKey(), 'active'),
                'outstanding' => $this->formatMoney($recovery->outstanding_amount_minor, $recovery->currency),
                'currency' => $recovery->currency,
                'opened_at' => $recovery->opened_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @param  Collection<int, FundingSettlement>  $settlements
     * @return array<int, array<string, mixed>>
     */
    private function treasuryPositions(Collection $settlements): array
    {
        $references = $settlements
            ->pluck('treasury_inventory_reference')
            ->filter(fn (mixed $reference): bool => is_string($reference) && $reference !== '')
            ->unique()
            ->values();

        if ($references->isEmpty()) {
            return [];
        }

        return TreasuryInventory::query()
            ->whereIn('inventory_reference', $references)
            ->orderBy('inventory_reference')
            ->get()
            ->map(fn (TreasuryInventory $inventory): array => [
                'provider' => $this->providerFromInventoryReference($inventory->inventory_reference),
                'currency' => $inventory->currency,
                'status' => $inventory->status,
                'recognized' => $this->formatMoney($inventory->balance_minor, $inventory->currency),
                'has_treasury_facts' => $inventory->version > 0,
            ])
            ->all();
    }

    private function providerFromInventoryReference(string $reference): string
    {
        $segments = explode(':', $reference);

        return $segments[1] ?? 'provider';
    }

    private function currency(?string $currency): string
    {
        return strtoupper(trim((string) ($currency ?: config('x-change.product.default_currency', 'PHP'))));
    }

    private function formatMoney(int $amountMinor, string $currency): string
    {
        return Number::currency($amountMinor / 100, in: $this->currency($currency));
    }
}
