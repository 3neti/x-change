<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use LBHurtado\XChange\Contracts\CockpitTreasuryAccessContract;
use LBHurtado\XChange\Data\Cockpit\CockpitFundingReadModelData;
use LBHurtado\XChange\Enums\AccountFundingReceiptStatus;
use LBHurtado\XChange\Models\AccountFundingReceipt;
use LBHurtado\XChange\Models\FundingAccountHold;
use LBHurtado\XChange\Models\FundingDestinationPreference;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\FundingReconciliationRequest;
use LBHurtado\XChange\Models\FundingRecovery;
use LBHurtado\XChange\Models\FundingSettlement;
use LBHurtado\XChange\Models\FundingSuspenseCase;
use LBHurtado\XChange\Models\SystemAccountFundingPayCodeIssuance;
use LBHurtado\XChange\Models\VoucherClaim;

class FundingCockpitReadModelProvider
{
    private const string SimulationProvider = 'qrph_simulator';

    public function __construct(
        private readonly FundingTreasuryPortfolioReadModel $treasury,
        private readonly CockpitTreasuryAccessContract $treasuryAccess,
    ) {}

    public function forOperator(Authenticatable $operator): CockpitFundingReadModelData
    {
        $actorType = $operator::class;
        $actorId = (string) $operator->getAuthIdentifier();
        $intentsQuery = FundingIntent::query()
            ->where('created_by_type', $actorType)
            ->where('created_by_id', $actorId);
        $operationalIntentsQuery = (clone $intentsQuery)
            ->where('provider_code', '!=', self::SimulationProvider);
        $operationalIntentIds = (clone $operationalIntentsQuery)->select('id');
        $settlementsQuery = FundingSettlement::query()
            ->whereIn('funding_intent_id', $operationalIntentIds);
        $settledStandingReceiptsQuery = AccountFundingReceipt::query()
            ->where('status', AccountFundingReceiptStatus::Settled)
            ->whereHas(
                'standingFundingAddress',
                fn (Builder $query): Builder => $query
                    ->where('owner_type', $actorType)
                    ->where('owner_id', $actorId),
            );
        $accountFundingClaimsQuery = $operator instanceof Model
            ? VoucherClaim::query()
                ->where('claim_type', 'account_funding')
                ->where('status', 'succeeded')
                ->whereIn(
                    'voucher_id',
                    SystemAccountFundingPayCodeIssuance::query()
                        ->visibleToRecipient($operator)
                        ->whereNull('metadata->custom->reviewed_funding->request_reference')
                        ->select('voucher_id'),
                )
            : VoucherClaim::query()->whereRaw('1 = 0');
        $openSuspenseCasesQuery = FundingSuspenseCase::query()
            ->whereIn('funding_intent_id', (clone $operationalIntentsQuery)->select('id'))
            ->whereIn('status', ['open', 'monitoring']);
        $openSuspenseCount = (clone $openSuspenseCasesQuery)->count();
        $openSuspenseCases = (clone $openSuspenseCasesQuery)
            ->with([
                'fundingIntent',
                'reconciliationRequests' => fn ($query) => $query
                    ->where('status', 'pending_approval'),
            ])
            ->latest('opened_at')
            ->limit(20)
            ->get();
        $activeRecoveriesQuery = FundingRecovery::query()
            ->whereIn('funding_intent_id', (clone $operationalIntentsQuery)->select('id'))
            ->where('outstanding_amount_minor', '>', 0);
        $recoveryMinor = (int) (clone $activeRecoveriesQuery)
            ->sum('outstanding_amount_minor');
        $activeRecoveries = (clone $activeRecoveriesQuery)
            ->latest('opened_at')
            ->limit(20)
            ->get();
        $canViewTreasuryControls = $this->treasuryAccess
            ->canViewTreasuryControls($operator);
        $canRefreshProviderLiquidity = $this->treasuryAccess
            ->canRefreshProviderLiquidity($operator);
        $canManageTreasuryReconciliation = $this->treasuryAccess
            ->canManageTreasuryReconciliation($operator);
        $treasuryPortfolio = $canViewTreasuryControls
            ? $this->treasury->forOperator($operator)
            : [];

        return new CockpitFundingReadModelData(
            summary: $this->summary(
                $operationalIntentsQuery,
                $settlementsQuery,
                $settledStandingReceiptsQuery,
                $accountFundingClaimsQuery,
                $openSuspenseCount,
                $recoveryMinor,
            ),
            providers: $this->providers($actorType, $actorId),
            intents: $this->intents($operationalIntentsQuery),
            suspense_cases: $this->suspenseCases(
                $openSuspenseCases,
                $canManageTreasuryReconciliation,
            ),
            approval_queue: $canManageTreasuryReconciliation
                ? $this->approvalQueue($actorType, $actorId)
                : [],
            recovery_holds: $this->recoveryHolds($activeRecoveries),
            treasury_positions: $this->treasuryPositions($treasuryPortfolio),
            treasury_portfolio: $treasuryPortfolio,
            controls: [
                'funding_intent_required' => true,
                'manual_balance_adjustment_enabled' => false,
                'webhook_direct_credit_enabled' => false,
                'authoritative_provider_verification_required' => true,
                'dual_control_reconciliation_required' => true,
                'live_provider_balance_connected' => (bool) config('x-change.cockpit.header_provider_balance.enabled', true),
                'can_view_treasury_controls' => $canViewTreasuryControls,
                'can_refresh_provider_liquidity' => $canRefreshProviderLiquidity,
                'can_manage_treasury_reconciliation' => $canManageTreasuryReconciliation,
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
                'treasury_controls_exposed' => $canViewTreasuryControls,
                'provider_liquidity_exposed' => $canViewTreasuryControls,
                'provider_inventory_exposed' => $canViewTreasuryControls,
                'reconciliation_controls_exposed' => $canViewTreasuryControls,
            ],
        );
    }

    /**
     * @param  Builder<FundingIntent>  $intentsQuery
     * @param  Builder<FundingSettlement>  $settlementsQuery
     * @param  Builder<AccountFundingReceipt>  $standingReceiptsQuery
     * @param  Builder<VoucherClaim>  $accountFundingClaimsQuery
     * @return array<string, int|string>
     */
    private function summary(
        Builder $intentsQuery,
        Builder $settlementsQuery,
        Builder $standingReceiptsQuery,
        Builder $accountFundingClaimsQuery,
        int $openSuspenseCount,
        int $recoveryMinor,
    ): array {
        $currency = $this->currency(
            (clone $settlementsQuery)->latest('settled_at')->value('currency')
                ?? (clone $standingReceiptsQuery)->latest('settled_at')->value('currency')
                ?? (clone $accountFundingClaimsQuery)->latest('completed_at')->value('currency'),
        );
        $settledMinor = (int) (clone $settlementsQuery)->sum('net_amount_minor')
            + (int) (clone $standingReceiptsQuery)->sum('net_amount_minor')
            + (int) (clone $accountFundingClaimsQuery)->sum('disbursed_amount_minor');

        return [
            'awaiting_funds' => (clone $intentsQuery)->where('status', 'awaiting_funds')->count(),
            'settled_funding' => $this->formatMoney($settledMinor, $currency),
            'open_suspense' => $openSuspenseCount,
            'recovery_outstanding' => $this->formatMoney($recoveryMinor, $currency),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function providers(string $actorType, string $actorId): array
    {
        $preferences = FundingDestinationPreference::query()
            ->with('providerAccountLink')
            ->where('owner_type', $actorType)
            ->where('owner_id', $actorId)
            ->get()
            ->keyBy('provider_code');

        return collect((array) config('x-change.funding.providers', []))
            ->filter(fn (mixed $provider): bool => is_array($provider))
            ->map(function (array $provider, string $code) use ($preferences): array {
                $enabled = ($provider['enabled'] ?? false) === true;
                $simulationOnly = $code === self::SimulationProvider;
                $preference = $preferences->get($code);
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
                        'qrph_simulator' => 'QR Ph Simulator',
                        default => str($code)->headline()->toString(),
                    },
                    'status' => ! $enabled
                        ? 'disabled'
                        : ($dedicatedReady ? 'available' : 'blocked'),
                    'authoritative_verification' => true,
                    'destination_mode' => $mode,
                    'destination_status' => $simulationOnly
                        ? 'simulation_only'
                        : ($mode === 'shared'
                            ? 'platform_managed'
                            : ($link?->verification_status ?? 'not_configured')),
                    'destination_reference' => $simulationOnly
                        ? 'Local simulated clearing'
                        : ($mode === 'dedicated'
                            ? $link?->display_reference
                            : 'Platform-managed'),
                    'simulation_only' => $simulationOnly,
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
            ->with('events')
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(function (FundingIntent $intent): array {
                $lastCheck = $intent->events->last(
                    fn ($event): bool => in_array($event->event_type, [
                        'provider_verification_started',
                        'provider_funds_not_observed',
                        'provider_verification_unavailable',
                        'provider_settlement_pending',
                        'provider_settlement_verified',
                        'provider_verification_indeterminate',
                        'provider_evidence_mismatch',
                    ], true),
                );
                $unexpired = $intent->expires_at?->isFuture() === true;
                $open = in_array($intent->status->value, [
                    'awaiting_funds',
                    'evidence_received',
                    'verifying',
                ], true);

                return [
                    'reference' => $intent->reference,
                    'provider' => $intent->provider_code,
                    'amount' => $this->formatMoney($intent->expected_amount_minor, $intent->currency),
                    'currency' => $intent->currency,
                    'status' => $intent->status->value,
                    'can_check_provider' => $intent->provider_code === 'netbank'
                        && $intent->status->value === 'awaiting_funds'
                        && $unexpired
                        && (bool) config('x-change.funding.providers.netbank.enabled', false),
                    'can_reopen_instructions' => $intent->provider_code === 'netbank'
                        && $open
                        && $unexpired,
                    'verification_status' => $intent->status->value === 'verifying'
                        ? 'checking'
                        : $intent->status->value,
                    'last_checked_at' => $lastCheck?->occurred_at?->toIso8601String(),
                    'created_at' => $intent->created_at?->toIso8601String(),
                    'expires_at' => $intent->expires_at?->toIso8601String(),
                    'settled_at' => $intent->settled_at?->toIso8601String(),
                ];
            })
            ->all();
    }

    /**
     * @param  Collection<int, FundingSuspenseCase>  $cases
     * @return array<int, array<string, mixed>>
     */
    private function suspenseCases(
        Collection $cases,
        bool $canManageTreasuryReconciliation,
    ): array {
        return $cases
            ->map(function (FundingSuspenseCase $case) use ($canManageTreasuryReconciliation): array {
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
                    'allowed_actions' => $canManageTreasuryReconciliation
                        && $pendingRequest === null
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
            ->whereHas(
                'suspenseCase',
                fn (Builder $query): Builder => $query
                    ->where('provider_code', '!=', self::SimulationProvider),
            )
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
     * @param  array<string, mixed>  $portfolio
     * @return array<int, array<string, mixed>>
     */
    private function treasuryPositions(array $portfolio): array
    {
        return collect((array) ($portfolio['connections'] ?? []))
            ->filter(
                static fn (mixed $connection): bool => is_array($connection)
                    && ($connection['provider_inventory_minor'] ?? null) !== null,
            )
            ->map(static fn (array $connection): array => [
                'provider' => $connection['provider'],
                'currency' => $connection['currency'],
                'status' => $connection['control_status'],
                'recognized' => $connection['provider_inventory'],
                'has_treasury_facts' => true,
            ])
            ->values()
            ->all();
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
