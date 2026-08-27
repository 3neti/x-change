<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use JsonSerializable;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimUrlQrRendererContract;
use LBHurtado\XChange\Contracts\CockpitCampaignIssuanceDraftAdapterContract;
use LBHurtado\XChange\Contracts\CockpitReadModelProviderContract;
use LBHurtado\XChange\Contracts\MoneyMovementAccountingDecisionContract;
use LBHurtado\XChange\Contracts\VoucherLiabilitySummaryContract;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Data\Cockpit\CockpitCampaignReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitDashboardActivityData;
use LBHurtado\XChange\Data\Cockpit\CockpitDashboardMetricData;
use LBHurtado\XChange\Data\Cockpit\CockpitDashboardPipelineStageData;
use LBHurtado\XChange\Data\Cockpit\CockpitDashboardReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitDashboardRiskSignalData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitPayCodeAttentionData;
use LBHurtado\XChange\Data\Cockpit\CockpitPayCodeCapabilityData;
use LBHurtado\XChange\Data\Cockpit\CockpitPayCodeExplorerFilterData;
use LBHurtado\XChange\Data\Cockpit\CockpitPayCodeExplorerStatsData;
use LBHurtado\XChange\Data\Cockpit\CockpitPayCodeInstructionBadgeData;
use LBHurtado\XChange\Data\Cockpit\CockpitPayCodeListReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitPayCodeListRecordData;
use LBHurtado\XChange\Data\Cockpit\CockpitPayCodePartyData;
use LBHurtado\XChange\Data\Cockpit\CockpitPayCodeRowActionData;
use LBHurtado\XChange\Data\Cockpit\CockpitPayCodeTerminalControlData;
use LBHurtado\XChange\Data\Cockpit\CockpitPayCodeTimingData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateActionData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateAuthorizationData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateAuthorizationGateData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateCampaignContextData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateClaimPreviewContractData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateDraftContractData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateFundingGateCheckData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateFundingGateData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateIdempotencyGateCheckData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateIdempotencyGateData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateMutationAuthorizationDecisionData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateMutationContractData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateMutationContractGateData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateMutationHandoffPlanData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateMutationHandoffPlanStepData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateMutationPreconditionsReviewData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateMutationPreconditionsReviewItemData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGeneratePricingGateCheckData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGeneratePricingGateData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGeneratePricingSummaryData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateReadModelData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateRuntimeInputData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateTemplateData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateValidationRedactionGateCheckData;
use LBHurtado\XChange\Data\Cockpit\CockpitQuickGenerateValidationRedactionGateData;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelBundleData;
use LBHurtado\XChange\Data\Cockpit\CockpitReadModelQueryData;
use LBHurtado\XChange\Data\Cockpit\CockpitVoucherEvidenceSummaryData;
use LBHurtado\XChange\Data\Cockpit\CockpitVoucherReadModelData;
use LBHurtado\XChange\Exceptions\VoucherNotFound;
use LBHurtado\XChange\Services\Slices\VoucherSlicePlanProjection;

class VoucherLifecycleCockpitReadModelProvider implements CockpitReadModelProviderContract
{
    public function __construct(
        private readonly VoucherLifecycleServiceContract $vouchers,
        private readonly NullCockpitReadModelProvider $fallback = new NullCockpitReadModelProvider,
        private readonly ?OptionalCockpitIntegrationReadModels $integrations = null,
        private readonly ?DurableCockpitOperatorIssuanceActivityReadModelProvider $operatorIssuanceActivity = null,
        private readonly ?CockpitCampaignIssuanceDraftAdapterContract $campaignDraftAdapter = null,
        private readonly ?VoucherLiabilitySummaryContract $liabilities = null,
        private readonly ?MoneyMovementAccountingDecisionContract $moneyMovementDecision = null,
        private readonly ?CockpitPayCodeDetailProjection $payCodeDetails = null,
        private readonly ?CockpitPayCodeIntegrationReferenceResolver $integrationReferences = null,
        private readonly ?ClaimUrlQrRendererContract $qrRenderer = null,
        private readonly ?VoucherSlicePlanProjection $slicePlans = null,
        private readonly ?PayCodeTerminalControlReadModel $terminalControls = null,
    ) {}

    public function forVoucher(CockpitReadModelQueryData $query): CockpitReadModelBundleData
    {
        $code = $this->normalizeCode($query->code);

        if ($code === null) {
            $fallback = $this->fallback->forVoucher($query);

            if (
                $this->integrations === null
                || ! $this->shouldHydrateIntegrationSummaries($query)
            ) {
                return $fallback;
            }

            return new CockpitReadModelBundleData(
                code: null,
                voucher: $fallback->voucher,
                execution: $fallback->execution,
                journal: $this->integrations->journal($query),
                actions: $this->integrations->actions($query),
                feedback: $this->integrations->feedback($query),
            );
        }

        try {
            $detail = $this->toArray($this->vouchers->showByCode($code));
        } catch (VoucherNotFound) {
            return $this->fallback->forVoucher(new CockpitReadModelQueryData(
                code: $code,
                operatorId: $query->operatorId,
                include: $query->include,
                correlationId: $query->correlationId,
            ));
        }

        if ($detail === []) {
            return $this->fallback->forVoucher(new CockpitReadModelQueryData(
                code: $code,
                operatorId: $query->operatorId,
                include: $query->include,
                correlationId: $query->correlationId,
            ));
        }

        $fallback = $this->fallback->forVoucher(new CockpitReadModelQueryData(
            code: $code,
            operatorId: $query->operatorId,
            include: $query->include,
            correlationId: $query->correlationId,
        ));
        $summary = $this->summary($detail, $code);
        $detailProjection = $this->payCodeDetails ?? new CockpitPayCodeDetailProjection;
        $integrationReferences = $this->integrationReferences ?? new CockpitPayCodeIntegrationReferenceResolver;
        $feedbackDeliveryIds = $integrationReferences->feedbackDeliveryIds($code);
        $voucherId = $this->nullableString($detail['voucher_id'] ?? $detail['id'] ?? null);
        $sliceProjection = [];

        if ($voucherId !== null && ctype_digit($voucherId)) {
            $voucher = Voucher::query()->find((int) $voucherId);

            if ($voucher instanceof Voucher) {
                $sliceProjection = ($this->slicePlans ?? app(VoucherSlicePlanProjection::class))->forVoucher($voucher);
            }
        }
        $execution = $fallback->execution;
        $journal = $this->integrations?->journal($query, $voucherId, $feedbackDeliveryIds) ?? $fallback->journal;
        $actions = $this->integrations?->actions($query) ?? $fallback->actions;
        $feedback = $this->integrations?->feedback($query, $feedbackDeliveryIds) ?? $fallback->feedback;

        return new CockpitReadModelBundleData(
            code: $this->summaryCode($detail, $code),
            voucher: new CockpitVoucherReadModelData(
                code: $this->summaryCode($detail, $code),
                status: $this->summaryStatus($detail),
                summary: $summary,
                overview: $detailProjection->overview($detail),
                instructions: $detailProjection->instructions($detail),
                claims: $detailProjection->claims($detail),
                slices: $detailProjection->slices($sliceProjection),
                settlement: $detailProjection->settlement($detail),
                treasury: $detailProjection->treasury($detail),
                collection: $detailProjection->collection($detail),
                evidence_summary: $this->voucherEvidenceSummary(
                    summary: $summary,
                    executionStatus: $execution->status,
                    journalStatus: $journal->status,
                    actionStatus: $actions->status,
                    feedbackStatus: $feedback->status,
                ),
                distribution_links: $this->distributionLinks(
                    $this->summaryCode($detail, $code),
                    data_get($detail, 'operational_status.can_claim') === true,
                ),
                redactions: [
                    'payloads' => 'sanitized-summary-only',
                    'excluded' => [
                        'id',
                        'voucher_id',
                        'issuer_id',
                        'approval',
                        'provider_payload',
                        'raw_payload',
                        'wallet',
                        'provider',
                    ],
                    'instructions' => 'human-readable-whitelist',
                    'claim_evidence' => 'authorized-summary-with-protected-artifacts',
                    'settlement_envelope' => 'readiness-summary-without-payload',
                ],
                authorized: true,
            ),
            execution: $execution,
            journal: $journal,
            actions: $actions,
            feedback: $feedback,
        );
    }

    private function shouldHydrateIntegrationSummaries(CockpitReadModelQueryData $query): bool
    {
        return collect($query->include)
            ->intersect(['journal', 'actions', 'feedback'])
            ->isNotEmpty();
    }

    /**
     * @return array<string, mixed>
     */
    private function distributionLinks(?string $code, bool $canClaim): array
    {
        if (
            ! $canClaim
            || $code === null
            || trim($code) === ''
            || ! Route::has('x-change.claim.show')
        ) {
            return [
                'schema' => 'x-change.cockpit.distribution-links.v1',
                'status' => 'unavailable',
                'available' => false,
                'read_only' => true,
                'delivery_enabled' => false,
                'reason' => $canClaim ? 'claim-route-unavailable' : 'pay-code-not-claimable',
                'redactions' => [
                    'payloads' => 'distribution-links-unavailable',
                ],
            ];
        }

        $redeemUrl = route('x-change.claim.show', ['code' => $code]);

        $links = [
            'schema' => 'x-change.cockpit.distribution-links.v1',
            'status' => 'available',
            'available' => true,
            'read_only' => true,
            'redeem_url' => $redeemUrl,
            'redeem_path' => route('x-change.claim.show', ['code' => $code], false),
            'source' => 'x-change.claim.show',
            'delivery_enabled' => false,
            'redactions' => [
                'payloads' => 'distribution-links-only',
                'secret_claim_material_exposed' => false,
                'provider_payloads_exposed' => false,
                'wallet_data_exposed' => false,
                'delivery_payloads_exposed' => false,
            ],
        ];

        $claimQr = $this->renderClaimQr($redeemUrl);

        if ($claimQr !== null) {
            $links['claim_qr'] = $claimQr;
        }

        return $links;
    }

    /**
     * Renders the canonical claim URL into a QR data URI using the injected
     * renderer. This never calls a provider and never mutates anything; a
     * rendering problem simply omits the QR field from an otherwise
     * unaffected read-only detail page.
     */
    private function renderClaimQr(string $redeemUrl): ?string
    {
        if ($this->qrRenderer === null) {
            return null;
        }

        try {
            return $this->qrRenderer->render($redeemUrl);
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<int, CockpitVoucherEvidenceSummaryData>
     */
    private function voucherEvidenceSummary(
        array $summary,
        string $executionStatus,
        string $journalStatus,
        string $actionStatus,
        string $feedbackStatus,
    ): array {
        $status = $this->stringValue($summary['display_status'] ?? null, $this->stringValue($summary['status'] ?? null, 'available'));
        $claimStatus = match (true) {
            ($summary['fully_claimed'] ?? null) === true => 'fully_claimed',
            ($summary['claimed'] ?? null) === true => 'partially_claimed',
            ($summary['claimed'] ?? null) === false || ($summary['fully_claimed'] ?? null) === false => 'not_claimed',
            default => 'not_wired',
        };

        return [
            new CockpitVoucherEvidenceSummaryData(
                key: 'lifecycle',
                label: 'Lifecycle facts',
                status: $status,
                description: 'Sanitized voucher lifecycle summary is available.',
                available: true,
                source: 'voucher',
            ),
            new CockpitVoucherEvidenceSummaryData(
                key: 'claim',
                label: 'Claim evidence',
                status: $claimStatus,
                description: 'Claim state is represented only as sanitized summary booleans.',
                available: $claimStatus !== 'not_wired',
                source: 'voucher-summary',
            ),
            new CockpitVoucherEvidenceSummaryData(
                key: 'approval',
                label: 'Approval evidence',
                status: 'redacted',
                description: 'Approval references and OTP metadata remain redacted from the voucher summary.',
                source: 'redaction-policy',
            ),
            new CockpitVoucherEvidenceSummaryData(
                key: 'execution',
                label: 'Execution evidence',
                status: $executionStatus,
                description: 'Execution evidence is read from the execution read model; this page does not invoke drivers.',
                available: $executionStatus === 'available',
                source: 'execution-read-model',
            ),
            new CockpitVoucherEvidenceSummaryData(
                key: 'journal',
                label: 'Journal evidence',
                status: $journalStatus,
                description: 'Journal evidence is read-only and only available through authorized journal read models.',
                available: $journalStatus === 'available',
                source: 'journal-read-model',
            ),
            new CockpitVoucherEvidenceSummaryData(
                key: 'actions',
                label: 'Action evidence',
                status: $actionStatus,
                description: 'Action evidence is presentation-only; Cockpit does not execute actions from Voucher Detail.',
                available: $actionStatus === 'available',
                source: 'action-read-model',
            ),
            new CockpitVoucherEvidenceSummaryData(
                key: 'feedback',
                label: 'Feedback evidence',
                status: $feedbackStatus,
                description: 'Feedback evidence is communication state only; Cockpit does not send feedback from Voucher Detail.',
                available: $feedbackStatus === 'available',
                source: 'feedback-read-model',
            ),
        ];
    }

    public function forDashboard(CockpitReadModelQueryData $query): CockpitDashboardReadModelData
    {
        $rows = collect($this->vouchers->list())
            ->map(fn (mixed $row): array => $this->toArray($row))
            ->filter(fn (array $row): bool => $this->summaryCode($row, '') !== '')
            ->values();

        $issued = $this->countStatus($rows, 'issued');
        $redeemed = $this->countStatus($rows, 'redeemed');
        $expired = $this->countStatus($rows, 'expired');
        $awaitingApproval = $rows
            ->filter(fn (array $row): bool => $this->stringValue($row['display_status'] ?? null, '') === 'awaiting_approval')
            ->count();
        $attention = $expired + $awaitingApproval;

        return new CockpitDashboardReadModelData(
            status: 'available',
            authorized: true,
            metrics: [
                new CockpitDashboardMetricData(
                    key: 'pay-codes-visible',
                    label: 'Pay Codes Visible',
                    value: (string) $rows->count(),
                    helper: 'Sanitized voucher lifecycle list rows',
                ),
                new CockpitDashboardMetricData(
                    key: 'issued-pay-codes',
                    label: 'Issued',
                    value: (string) $issued,
                    helper: 'Read-only lifecycle summary',
                    tone: 'healthy',
                ),
                new CockpitDashboardMetricData(
                    key: 'redeemed-pay-codes',
                    label: 'Redeemed',
                    value: (string) $redeemed,
                    helper: 'Includes awaiting approval display states',
                    tone: 'healthy',
                ),
                new CockpitDashboardMetricData(
                    key: 'attention-pay-codes',
                    label: 'Needs Attention',
                    value: (string) $attention,
                    helper: 'Expired or awaiting approval summaries only',
                    tone: 'warning',
                ),
                ...$this->liabilityMetrics($query),
            ],
            pipeline: [
                new CockpitDashboardPipelineStageData(
                    key: 'issued',
                    label: 'Issued',
                    value: (string) $issued,
                ),
                new CockpitDashboardPipelineStageData(
                    key: 'redeemed',
                    label: 'Redeemed',
                    value: (string) $redeemed,
                    tone: 'healthy',
                ),
                new CockpitDashboardPipelineStageData(
                    key: 'expired',
                    label: 'Expired',
                    value: (string) $expired,
                    tone: 'warning',
                ),
                new CockpitDashboardPipelineStageData(
                    key: 'awaiting-approval',
                    label: 'Awaiting Approval',
                    value: (string) $awaitingApproval,
                    tone: 'warning',
                ),
            ],
            risk_signals: [
                new CockpitDashboardRiskSignalData(
                    key: 'expired-pay-codes',
                    label: 'Expired Pay Codes',
                    value: $expired.' sanitized summaries',
                    severity: 'warning',
                ),
                new CockpitDashboardRiskSignalData(
                    key: 'awaiting-approval',
                    label: 'Awaiting Approval',
                    value: $awaitingApproval.' sanitized summaries',
                    severity: 'watch',
                ),
            ],
            activity: $this->dashboardActivity($rows, $query),
            redactions: [
                'payloads' => 'sanitized-dashboard-summary-only',
                'excluded' => $this->excludedPayloadKeys(),
            ],
        );
    }

    public function forQuickGenerate(CockpitReadModelQueryData $query): CockpitQuickGenerateReadModelData
    {
        return new CockpitQuickGenerateReadModelData(
            status: 'available',
            authorized: true,
            templates: [
                new CockpitQuickGenerateTemplateData(
                    key: 'money-changer',
                    name: 'Money Changer',
                    description: 'Fast cash-out Pay Code for branch counter operations.',
                    profile: 'branch',
                    estimated_time: 'Under 5 seconds',
                ),
                new CockpitQuickGenerateTemplateData(
                    key: 'blank-pay-code',
                    name: 'Blank Pay Code',
                    description: 'Begin with only the essentials and shape the Pay Code yourself.',
                    profile: 'custom',
                    estimated_time: 'Build your own',
                ),
                new CockpitQuickGenerateTemplateData(
                    key: 'ofw-remittance',
                    name: 'OFW Remittance',
                    description: 'Template-first remittance issuance with recipient details.',
                    profile: 'operations',
                    estimated_time: 'Pending runtime inputs',
                ),
                new CockpitQuickGenerateTemplateData(
                    key: 'settlement-envelope',
                    name: 'Settlement Envelope',
                    description: 'Complex settlement issuance remains deferred to later slices.',
                    profile: 'settlement',
                    estimated_time: 'Deferred',
                    disabled: true,
                ),
            ],
            runtime_inputs: [
                new CockpitQuickGenerateRuntimeInputData(
                    key: 'amount',
                    label: 'Amount',
                    value: 'Use the Quick Generate form',
                    helper: 'Pricing and funding preflights appear after a successful form submit.',
                ),
                new CockpitQuickGenerateRuntimeInputData(
                    key: 'recipient',
                    label: 'Recipient',
                    value: 'Use the Quick Generate form',
                    helper: 'Recipient reference is submitted through the existing issuance handoff.',
                ),
                new CockpitQuickGenerateRuntimeInputData(
                    key: 'purpose',
                    label: 'Purpose',
                    value: 'Optional form note',
                    helper: 'Purpose/message is passed as operator-safe issuance context.',
                ),
            ],
            pricing_summaries: [
                new CockpitQuickGeneratePricingSummaryData(
                    key: 'pricing',
                    label: 'Pricing Estimate',
                    value: 'Shown after submit',
                    helper: 'The result panel displays the operator-safe pricing preflight returned by the existing runtime.',
                ),
                new CockpitQuickGeneratePricingSummaryData(
                    key: 'funding',
                    label: 'Funding Impact',
                    value: 'Shown after submit',
                    helper: 'The result panel displays the operator-safe funding preflight; reservation and money movement remain behind existing issuance services.',
                ),
                new CockpitQuickGeneratePricingSummaryData(
                    key: 'execution',
                    label: 'Execution Summary',
                    value: 'Existing handoff',
                    helper: 'Quick Generate compiles a draft and hands off to GeneratePayCode; execution semantics stay voucher-owned.',
                ),
            ],
            pricing_gate: new CockpitQuickGeneratePricingGateData(
                status: 'runtime-informational',
                checks: [
                    new CockpitQuickGeneratePricingGateCheckData(
                        key: 'template-selected',
                        label: 'Template Selected',
                        status: 'passed',
                        reason: 'The Money Changer template is selected by default for the current Quick Generate runtime.',
                    ),
                    new CockpitQuickGeneratePricingGateCheckData(
                        key: 'amount-input-present',
                        label: 'Amount Input Present',
                        status: 'passed',
                        reason: 'The Quick Generate form accepts an operator amount and submits it to the existing issuance handoff.',
                    ),
                    new CockpitQuickGeneratePricingGateCheckData(
                        key: 'pricing-service-wired',
                        label: 'Pricing Service Wired',
                        status: 'passed',
                        reason: 'The mutation result exposes an operator-safe pricing preflight after GeneratePayCode completes.',
                    ),
                    new CockpitQuickGeneratePricingGateCheckData(
                        key: 'funding-source-selected',
                        label: 'Funding Source Selected',
                        status: 'runtime-diagnostic',
                        reason: 'Funding source details remain redacted; the operator sees only the safe funding preflight result after submit.',
                    ),
                    new CockpitQuickGeneratePricingGateCheckData(
                        key: 'funds-reservation',
                        label: 'Funds Reservation',
                        status: 'blocked',
                        reason: 'Cockpit does not reserve, debit, or hold funds directly; those effects remain behind the existing issuance services.',
                    ),
                    new CockpitQuickGeneratePricingGateCheckData(
                        key: 'provider-fee-quote',
                        label: 'Provider Fee Quote',
                        status: 'blocked',
                        reason: 'Cockpit does not call provider quote APIs directly.',
                    ),
                ],
                redactions: [
                    'payloads' => 'pricing-gates-only',
                    'excluded' => [
                        'pricing_breakdown',
                        'funding_source',
                        'wallet',
                        'balance',
                        'account_number',
                        'provider_payload',
                        'raw_payload',
                    ],
                ],
            ),
            funding_gate: new CockpitQuickGenerateFundingGateData(
                status: 'runtime-informational',
                checks: [
                    new CockpitQuickGenerateFundingGateCheckData(
                        key: 'funding-policy-known',
                        label: 'Funding Policy Known',
                        status: 'passed',
                        reason: 'Funding preflight is represented as an operator-safe result after Quick Generate submits.',
                    ),
                    new CockpitQuickGenerateFundingGateCheckData(
                        key: 'issuer-wallet-identified',
                        label: 'Issuer Account Identified',
                        status: 'runtime-diagnostic',
                        reason: 'Issuer funding details are evaluated by the existing issuance path and redacted from the Cockpit read model.',
                    ),
                    new CockpitQuickGenerateFundingGateCheckData(
                        key: 'wallet-balance-available',
                        label: 'Client Funds Available',
                        status: 'runtime-diagnostic',
                        reason: 'The operator sees only the safe funding preflight summary returned by the issuance runtime.',
                    ),
                    new CockpitQuickGenerateFundingGateCheckData(
                        key: 'sufficient-funds',
                        label: 'Sufficient Funds',
                        status: 'runtime-diagnostic',
                        reason: 'Sufficiency is reported as an operator-safe preflight after submit; raw wallet data remains hidden.',
                    ),
                    new CockpitQuickGenerateFundingGateCheckData(
                        key: 'funds-reservation-ready',
                        label: 'Funds Reservation Ready',
                        status: 'blocked',
                        reason: 'Cockpit does not reserve, hold, debit, or transfer funds directly.',
                    ),
                    new CockpitQuickGenerateFundingGateCheckData(
                        key: 'provider-funding-ready',
                        label: 'Provider Funding Ready',
                        status: 'blocked',
                        reason: 'Cockpit does not call provider funding or account-readiness services directly.',
                    ),
                ],
                redactions: [
                    'payloads' => 'funding-gates-only',
                    'excluded' => [
                        'funding_source',
                        'wallet',
                        'balance',
                        'available_balance',
                        'account_number',
                        'provider_wallet',
                        'provider_payload',
                        'raw_payload',
                    ],
                ],
            ),
            idempotency_gate: new CockpitQuickGenerateIdempotencyGateData(
                status: 'backend-ready',
                checks: [
                    new CockpitQuickGenerateIdempotencyGateCheckData(
                        key: 'idempotency-policy-known',
                        label: 'Idempotency Policy Known',
                        status: 'passed',
                        reason: 'Cockpit uses the existing x-change idempotency policy for Quick Generate mutation requests.',
                    ),
                    new CockpitQuickGenerateIdempotencyGateCheckData(
                        key: 'idempotency-key-source-defined',
                        label: 'Idempotency Key Source Defined',
                        status: 'passed',
                        reason: 'Cockpit accepts the configured Idempotency-Key header on the Quick Generate mutation route.',
                    ),
                    new CockpitQuickGenerateIdempotencyGateCheckData(
                        key: 'payload-fingerprint-defined',
                        label: 'Payload Fingerprint Defined',
                        status: 'passed',
                        reason: 'Cockpit delegates payload fingerprinting to the existing IdempotencyService.',
                    ),
                    new CockpitQuickGenerateIdempotencyGateCheckData(
                        key: 'replay-lookup-ready',
                        label: 'Replay Lookup Ready',
                        status: 'passed',
                        reason: 'Cockpit replays stored redacted operator responses for matching keys and payloads.',
                    ),
                    new CockpitQuickGenerateIdempotencyGateCheckData(
                        key: 'conflict-response-ready',
                        label: 'Conflict Response Ready',
                        status: 'passed',
                        reason: 'Cockpit returns the existing idempotency conflict response before a second issuance action call.',
                    ),
                    new CockpitQuickGenerateIdempotencyGateCheckData(
                        key: 'ttl-policy-ready',
                        label: 'TTL Policy Ready',
                        status: 'passed',
                        reason: 'Cockpit uses the existing IdempotencyService TTL configuration.',
                    ),
                ],
                redactions: [
                    'payloads' => 'idempotency-gates-only',
                    'excluded' => [
                        'idempotency_key',
                        'request_payload',
                        'payload_fingerprint',
                        'stored_response',
                        'replay_payload',
                        'cache_key',
                        'raw_payload',
                    ],
                ],
            ),
            validation_redaction_gate: new CockpitQuickGenerateValidationRedactionGateData(
                status: 'backend-ready',
                checks: [
                    new CockpitQuickGenerateValidationRedactionGateCheckData(
                        key: 'request-schema-known',
                        label: 'Request Schema Known',
                        status: 'passed',
                        reason: 'The Quick Generate mutation request shape is known and handled by the existing handoff route.',
                    ),
                    new CockpitQuickGenerateValidationRedactionGateCheckData(
                        key: 'required-fields-defined',
                        label: 'Required Fields Defined',
                        status: 'passed',
                        reason: 'The Quick Generate form submits the required issuance fields to the existing GeneratePayCode request path.',
                    ),
                    new CockpitQuickGenerateValidationRedactionGateCheckData(
                        key: 'validation-rules-wired',
                        label: 'Validation Rules Wired',
                        status: 'passed',
                        reason: 'The Cockpit handoff route uses GeneratePayCodeRequest-compatible validation.',
                    ),
                    new CockpitQuickGenerateValidationRedactionGateCheckData(
                        key: 'sensitive-fields-redacted',
                        label: 'Sensitive Fields Redacted',
                        status: 'passed',
                        reason: 'Operator responses exclude raw payloads, provider payloads, wallet details, and idempotency internals.',
                    ),
                    new CockpitQuickGenerateValidationRedactionGateCheckData(
                        key: 'sanitized-preview-ready',
                        label: 'Sanitized Preview Ready',
                        status: 'passed',
                        reason: 'The result panel renders sanitized generated facts and preflight summaries only.',
                    ),
                    new CockpitQuickGenerateValidationRedactionGateCheckData(
                        key: 'validation-error-contract-ready',
                        label: 'Validation Error Contract Ready',
                        status: 'passed',
                        reason: 'Validation errors remain on the Quick Generate form through the Inertia handoff route.',
                    ),
                ],
                redactions: [
                    'payloads' => 'validation-redaction-gates-only',
                    'excluded' => [
                        'request_payload',
                        'validated_payload',
                        'validation_errors',
                        'mobile',
                        'email',
                        'recipient_reference',
                        'account_number',
                        'raw_payload',
                    ],
                ],
            ),
            mutation_handoff_plan: new CockpitQuickGenerateMutationHandoffPlanData(
                status: 'backend-handoff-wired',
                steps: [
                    new CockpitQuickGenerateMutationHandoffPlanStepData(
                        key: 'existing-issuance-owner-identified',
                        label: 'Existing Issuance Owner Identified',
                        status: 'passed',
                        reason: 'Quick Generate must hand off to the existing x-change issuance owner instead of inventing Cockpit generation behavior.',
                    ),
                    new CockpitQuickGenerateMutationHandoffPlanStepData(
                        key: 'generate-pay-code-action-handoff',
                        label: 'GeneratePayCode Action Handoff',
                        status: 'passed',
                        reason: 'Cockpit POST route calls the existing GeneratePayCode action in Wave 1C.',
                    ),
                    new CockpitQuickGenerateMutationHandoffPlanStepData(
                        key: 'generate-pay-code-controller-handoff',
                        label: 'GeneratePayCodeController Handoff',
                        status: 'confirmed',
                        reason: 'The public API route remains owned by GeneratePayCodeController while Cockpit shares the action directly.',
                    ),
                    new CockpitQuickGenerateMutationHandoffPlanStepData(
                        key: 'preconditions-green',
                        label: 'Preconditions Green',
                        status: 'blocked',
                        reason: 'Provider, journal, action, and feedback side effects remain separately gated.',
                    ),
                    new CockpitQuickGenerateMutationHandoffPlanStepData(
                        key: 'side-effect-boundary-confirmed',
                        label: 'Side Effect Boundary Confirmed',
                        status: 'passed',
                        reason: 'Cockpit does not call providers, wallets, journal, action, or feedback directly; issuance side effects remain behind GeneratePayCode.',
                    ),
                    new CockpitQuickGenerateMutationHandoffPlanStepData(
                        key: 'operator-response-contract-ready',
                        label: 'Operator Response Contract Ready',
                        status: 'passed',
                        reason: 'Cockpit returns only operator-safe generated facts from the existing issuance action.',
                    ),
                ],
                redactions: [
                    'payloads' => 'mutation-handoff-plan-only',
                    'excluded' => [
                        'request_payload',
                        'validated_payload',
                        'mutation_payload',
                        'issued_voucher',
                        'generated_pay_code',
                        'provider_payload',
                        'wallet',
                        'journal_payload',
                        'action_payload',
                        'feedback_payload',
                        'side_effect_result',
                        'raw_payload',
                    ],
                ],
            ),
            mutation_preconditions_review: new CockpitQuickGenerateMutationPreconditionsReviewData(
                status: 'existing-handoff-ready',
                recommendation: 'use-existing-issuance-handoff',
                items: [
                    new CockpitQuickGenerateMutationPreconditionsReviewItemData(
                        key: 'authorization-ready',
                        label: 'Authorization Ready',
                        status: 'passed',
                        reason: 'The authenticated Cockpit route may submit through the approved GeneratePayCode handoff.',
                    ),
                    new CockpitQuickGenerateMutationPreconditionsReviewItemData(
                        key: 'pricing-ready',
                        label: 'Pricing Ready',
                        status: 'runtime-informational',
                        reason: 'Pricing preflight is available in the operator-safe result panel after submit.',
                    ),
                    new CockpitQuickGenerateMutationPreconditionsReviewItemData(
                        key: 'funding-ready',
                        label: 'Funding Ready',
                        status: 'runtime-informational',
                        reason: 'Funding preflight is available in the operator-safe result panel after submit; raw wallet details remain redacted.',
                    ),
                    new CockpitQuickGenerateMutationPreconditionsReviewItemData(
                        key: 'idempotency-ready',
                        label: 'Idempotency Ready',
                        status: 'passed',
                        reason: 'Wave 1D wires idempotency key extraction, payload fingerprinting, replay lookup, conflict response, and TTL policy through the existing IdempotencyService.',
                    ),
                    new CockpitQuickGenerateMutationPreconditionsReviewItemData(
                        key: 'validation-redaction-ready',
                        label: 'Validation and Redaction Ready',
                        status: 'passed',
                        reason: 'GeneratePayCodeRequest-compatible validation and operator-safe response redaction are wired.',
                    ),
                    new CockpitQuickGenerateMutationPreconditionsReviewItemData(
                        key: 'handoff-ready',
                        label: 'Handoff Ready',
                        status: 'passed',
                        reason: 'Wave 1C wires the GeneratePayCode action handoff and confirms the public GeneratePayCodeController route remains unchanged.',
                    ),
                    new CockpitQuickGenerateMutationPreconditionsReviewItemData(
                        key: 'operator-response-ready',
                        label: 'Operator Response Ready',
                        status: 'passed',
                        reason: 'Cockpit returns a redacted operator result with generated Pay Code, preflights, and activity runtime diagnostics.',
                    ),
                ],
                redactions: [
                    'payloads' => 'mutation-preconditions-review-only',
                    'excluded' => [
                        'request_payload',
                        'validated_payload',
                        'precondition_payload',
                        'mutation_approval',
                        'mutation_route',
                        'issued_voucher',
                        'generated_pay_code',
                        'provider_payload',
                        'wallet',
                        'side_effect_result',
                        'raw_payload',
                    ],
                ],
            ),
            mutation_authorization_decision: new CockpitQuickGenerateMutationAuthorizationDecisionData(
                status: 'approved-handoff',
                decision: 'authorized_existing_handoff',
                required_approval: 'completed-for-existing-generate-pay-code-handoff',
                rationale: 'Cockpit may submit Quick Generate through the existing GeneratePayCode action without inventing a parallel issuance runtime.',
                next_step: 'keep-provider-journal-action-feedback-mutations-separately-gated',
                redactions: [
                    'payloads' => 'mutation-authorization-decision-only',
                    'excluded' => [
                        'request_payload',
                        'validated_payload',
                        'mutation_payload',
                        'approval_payload',
                        'route_definition',
                        'issued_voucher',
                        'generated_pay_code',
                        'provider_payload',
                        'wallet',
                        'side_effect_result',
                        'raw_payload',
                    ],
                ],
            ),
            mutation_contract: new CockpitQuickGenerateMutationContractData(
                status: 'existing_issuance_handoff_registered',
                authorization: 'operator-authenticated-handoff-route',
                route: 'x-change.cockpit.quick-generate.store',
                route_url: Route::has('x-change.cockpit.quick-generate.store')
                    ? route('x-change.cockpit.quick-generate.store', [], false)
                    : null,
                request_adapter: 'GeneratePayCodeRequest-compatible-adapter',
                issuance_owner: 'GeneratePayCode',
                idempotency: 'replay-safe-route-registered',
                response_contract: 'operator-safe-redacted-result',
                runtime_enabled: true,
                gates: [
                    new CockpitQuickGenerateMutationContractGateData(
                        key: 'route-contract-defined',
                        label: 'Route Contract Defined',
                        status: 'passed',
                        decision: 'POST route is registered under the reserved route name.',
                        reason: 'Wave 1C reuses the Wave 1B route shell for the existing issuance handoff.',
                    ),
                    new CockpitQuickGenerateMutationContractGateData(
                        key: 'request-adapter-defined',
                        label: 'Request Adapter Defined',
                        status: 'passed',
                        decision: 'Cockpit route uses GeneratePayCodeRequest validation.',
                        reason: 'Cockpit does not invent a second issuance validation language.',
                    ),
                    new CockpitQuickGenerateMutationContractGateData(
                        key: 'issuance-owner-confirmed',
                        label: 'Issuance Owner Confirmed',
                        status: 'passed',
                        decision: 'GeneratePayCode remains the issuance owner.',
                        reason: 'Cockpit is an operator shell and must hand off to existing x-change issuance behavior.',
                    ),
                    new CockpitQuickGenerateMutationContractGateData(
                        key: 'idempotency-required',
                        label: 'Idempotency Required',
                        status: 'passed',
                        decision: 'Idempotency key and replay handling are wired through the existing IdempotencyService.',
                        reason: 'Repeated operator submits with the same key and payload replay the stored operator response without duplicate issuance.',
                    ),
                    new CockpitQuickGenerateMutationContractGateData(
                        key: 'operator-response-redacted',
                        label: 'Operator Response Redacted',
                        status: 'passed',
                        decision: 'Response exposes operator-safe generated facts only.',
                        reason: 'Provider payloads, wallet data, raw voucher payloads, secrets, and internal IDs remain excluded.',
                    ),
                    new CockpitQuickGenerateMutationContractGateData(
                        key: 'ui-submit-disabled',
                        label: 'UI Submit Enabled',
                        status: 'passed',
                        decision: 'Cockpit UI may submit only to the idempotency-protected route URL from the read model.',
                        reason: 'Wave 1E enables a guarded submit control while keeping refresh, redirect, and optimistic UI deferred.',
                    ),
                ],
                allowed_methods: ['GET', 'POST'],
                redactions: [
                    'payloads' => 'mutation-contract-only',
                    'excluded' => [
                        'request_payload',
                        'validated_payload',
                        'idempotency_key',
                        'payload_fingerprint',
                        'issued_voucher',
                        'generated_pay_code',
                        'provider_payload',
                        'wallet',
                        'balance',
                        'funding_source',
                        'journal_payload',
                        'action_payload',
                        'feedback_payload',
                        'raw_payload',
                    ],
                ],
            ),
            claim_preview_contract: new CockpitQuickGenerateClaimPreviewContractData(
                status: 'registered',
                route: 'x-change.cockpit.quick-generate.claim-previews.store',
                route_url: Route::has('x-change.cockpit.quick-generate.claim-previews.store')
                    ? route('x-change.cockpit.quick-generate.claim-previews.store', [], false)
                    : null,
                redactions: [
                    'payloads' => 'voucher-instructions-preview-contract-only',
                    'excluded' => [
                        'provider_payload',
                        'wallet',
                        'debit',
                        'raw_browser_trace',
                        'submitted_claim',
                    ],
                ],
            ),
            draft_contract: new CockpitQuickGenerateDraftContractData(
                status: 'draft_only',
                template_key: 'money-changer',
                currency: 'PHP',
                redactions: [
                    'payloads' => 'draft-shape-only',
                    'excluded' => [
                        'mobile',
                        'email',
                        'wallet',
                        'balance',
                        'provider_payload',
                        'raw_payload',
                        'account_number',
                        'pricing_breakdown',
                        'funding_source',
                        'issuer_id',
                    ],
                ],
            ),
            authorization: new CockpitQuickGenerateAuthorizationData(
                status: 'runtime-ready',
                gates: [
                    new CockpitQuickGenerateAuthorizationGateData(
                        key: 'operator-authenticated',
                        label: 'Operator Authenticated',
                        status: 'passed',
                        reason: 'Authenticated Cockpit GET route resolved.',
                    ),
                    new CockpitQuickGenerateAuthorizationGateData(
                        key: 'can-view-cockpit',
                        label: 'Can View Cockpit',
                        status: 'passed',
                        reason: 'Read-only Cockpit access is available.',
                    ),
                    new CockpitQuickGenerateAuthorizationGateData(
                        key: 'can-generate-pay-code',
                        label: 'Can Generate Pay Code',
                        status: 'passed',
                        reason: 'The approved Cockpit Quick Generate mutation route submits through the existing GeneratePayCode action.',
                    ),
                    new CockpitQuickGenerateAuthorizationGateData(
                        key: 'can-call-providers',
                        label: 'Can Call Providers',
                        status: 'blocked',
                        reason: 'Provider calls are outside the Slice 19 boundary.',
                    ),
                    new CockpitQuickGenerateAuthorizationGateData(
                        key: 'can-move-money',
                        label: 'Can Move Money',
                        status: 'blocked',
                        reason: 'Money movement remains disabled in Cockpit.',
                    ),
                ],
                redactions: [
                    'payloads' => 'authorization-gates-only',
                    'excluded' => [
                        'roles',
                        'permissions',
                        'policy_payload',
                        'tenant_payload',
                        'provider_payload',
                        'raw_payload',
                    ],
                ],
            ),
            action: new CockpitQuickGenerateActionData(
                enabled: true,
                reason: 'existing-issuance-handoff-enabled',
            ),
            campaign_context: $this->quickGenerateCampaignContext($query),
            redactions: [
                'payloads' => 'sanitized-quick-generate-catalog-only',
                'excluded' => [
                    'wallet',
                    'balance',
                    'provider_payload',
                    'raw_payload',
                    'account_number',
                    'pricing_breakdown',
                    'funding_source',
                    'issuer_id',
                ],
            ],
        );
    }

    private function quickGenerateCampaignContext(CockpitReadModelQueryData $query): CockpitQuickGenerateCampaignContextData
    {
        if ($query->campaignPlanningKey === null && $query->campaignExecutionId === null && $query->campaignId === null) {
            return new CockpitQuickGenerateCampaignContextData;
        }

        $campaignContext = [
            'planning_key' => $query->campaignPlanningKey,
            'execution_id' => $query->campaignExecutionId,
            'campaign_id' => $query->campaignId,
            'audience_id' => $query->campaignAudienceId,
            'recipient_id' => $query->campaignRecipientId,
            'source' => $query->campaignSource ?? 'campaign_cockpit',
            'template_key' => $query->campaignTemplateKey,
            'amount' => $query->campaignAmount,
            'currency' => $query->campaignCurrency,
            'recipient_reference' => $query->campaignRecipientReference,
            'purpose' => $query->campaignPurpose,
            'metadata' => [
                'read_only' => true,
                'source' => 'x-change.cockpit.quick-generate',
            ],
        ];

        $draft = ($this->campaignDraftAdapter ?? new DefaultCockpitCampaignIssuanceDraftAdapter)
            ->fromCampaignContext($campaignContext);

        return new CockpitQuickGenerateCampaignContextData(
            status: 'available',
            authorized: true,
            planning_key: $query->campaignPlanningKey,
            execution_id: $query->campaignExecutionId,
            campaign_id: $query->campaignId,
            audience_id: $query->campaignAudienceId,
            recipient_id: $query->campaignRecipientId,
            source: $query->campaignSource ?? 'campaign_cockpit',
            draft: $draft,
            redactions: [
                'payloads' => 'campaign-context-prefill-only',
                'excluded' => [
                    'campaign_payload',
                    'recipient_payload',
                    'provider_payload',
                    'wallet',
                    'balance',
                    'raw_payload',
                ],
            ],
        );
    }

    public function forCampaignAdoption(CockpitReadModelQueryData $query): CockpitCampaignReadModelData
    {
        $readModel = $this->integrations === null
            ? $this->fallback->forCampaignAdoption($query)
            : $this->integrations->campaignAdoption($query);

        return $this->withCampaignQuickGenerateLink($readModel, $query);
    }

    private function withCampaignQuickGenerateLink(CockpitCampaignReadModelData $readModel, CockpitReadModelQueryData $query): CockpitCampaignReadModelData
    {
        return new CockpitCampaignReadModelData(
            schema: $readModel->schema,
            status: $readModel->status,
            authorized: $readModel->authorized,
            source: $readModel->source,
            surfaces: $readModel->surfaces,
            facts: $readModel->facts,
            mutation: $readModel->mutation,
            quick_generate_link: $this->campaignQuickGenerateLink($query, $this->campaignAdapterSourceContext($readModel)),
            recipient_quick_generate_links: $this->campaignRecipientQuickGenerateLinks($query, $readModel),
            redactions: $readModel->redactions,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function campaignQuickGenerateLink(CockpitReadModelQueryData $query, array $adapterContext = []): array
    {
        $planningKey = $this->nullableString($query->campaignPlanningKey ?? $query->code);
        $executionId = $this->nullableString($query->campaignExecutionId ?? $query->correlationId);

        if ($planningKey === null || ! Route::has('x-change.cockpit.quick-generate')) {
            return [
                'schema' => 'x-change.cockpit.campaign-quick-generate-link.v1',
                'status' => 'not_available',
                'enabled' => false,
                'label' => 'Open Quick Generate',
                'href' => null,
                'route' => 'x-change.cockpit.quick-generate',
                'read_only' => true,
                'mutates_campaign' => false,
                'planning_key' => $planningKey,
                'execution_id' => $executionId,
                'campaign_id' => $this->nullableString($query->campaignId),
                'audience_id' => $this->nullableString($query->campaignAudienceId),
                'recipient_id' => $this->nullableString($query->campaignRecipientId),
                'source' => $this->nullableString($query->campaignSource),
                'draft' => null,
                'redactions' => ['payloads' => 'not-loaded'],
            ];
        }

        $source = $this->nullableString($query->campaignSource)
            ?? $this->nullableString($adapterContext['source'] ?? null)
            ?? 'campaign_cockpit';
        $adapterDraft = $this->campaignSourceLinkDraft($adapterContext);
        $draft = [
            'template_key' => $this->nullableString($query->campaignTemplateKey)
                ?? $this->nullableString($adapterContext['template_key'] ?? null)
                ?? $this->nullableString($adapterDraft['template_key'] ?? null),
            'amount' => $this->amountValue($query->campaignAmount)
                ?? $this->amountValue($adapterContext['amount'] ?? null)
                ?? $this->amountValue($adapterDraft['amount'] ?? null),
            'currency' => $this->nullableString($query->campaignCurrency)
                ?? $this->nullableString($adapterContext['currency'] ?? null)
                ?? $this->nullableString($adapterDraft['currency'] ?? null)
                ?? 'PHP',
            'recipient_reference' => $this->nullableString($query->campaignRecipientReference)
                ?? $this->nullableString($adapterContext['recipient_reference'] ?? null)
                ?? $this->nullableString($adapterDraft['recipient_reference'] ?? null),
            'purpose' => $this->nullableString($query->campaignPurpose)
                ?? $this->nullableString($adapterContext['purpose'] ?? null)
                ?? $this->nullableString($adapterDraft['purpose'] ?? null),
        ];
        $campaignId = $this->nullableString($query->campaignId)
            ?? $this->nullableString($adapterContext['campaign_id'] ?? null);
        $audienceId = $this->nullableString($query->campaignAudienceId)
            ?? $this->nullableString($adapterContext['audience_id'] ?? null);
        $recipientId = $this->nullableString($query->campaignRecipientId)
            ?? $this->nullableString($adapterContext['recipient_id'] ?? null);
        $parameters = array_filter([
            'campaign_planning_key' => $planningKey,
            'campaign_execution_id' => $executionId,
            'campaign_id' => $campaignId,
            'campaign_audience_id' => $audienceId,
            'campaign_recipient_id' => $recipientId,
            'campaign_source' => $source,
            'campaign_template_key' => $draft['template_key'],
            'campaign_amount' => $draft['amount'],
            'campaign_currency' => $draft['currency'],
            'campaign_recipient_reference' => $draft['recipient_reference'],
            'campaign_purpose' => $draft['purpose'],
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        return [
            'schema' => 'x-change.cockpit.campaign-quick-generate-link.v1',
            'status' => 'available',
            'enabled' => true,
            'label' => 'Open Quick Generate',
            'href' => route('x-change.cockpit.quick-generate', $parameters),
            'route' => 'x-change.cockpit.quick-generate',
            'read_only' => true,
            'mutates_campaign' => false,
            'planning_key' => $planningKey,
            'execution_id' => $executionId,
            'campaign_id' => $campaignId,
            'audience_id' => $audienceId,
            'recipient_id' => $recipientId,
            'source' => $source,
            'draft' => $draft,
            'redactions' => [
                'payloads' => 'campaign-source-link-query-only',
                'excluded' => [
                    'campaign_payload',
                    'recipient_payload',
                    'provider_payload',
                    'wallet',
                    'balance',
                    'raw_payload',
                    'pay_code_generation_payload',
                    'delivery_dispatch_payload',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function campaignAdapterSourceContext(CockpitCampaignReadModelData $readModel): array
    {
        $metadata = $this->toArray($readModel->facts['metadata'] ?? []);
        $candidates = [
            $metadata['quick_generate_context'] ?? null,
            $metadata['quick_generate_source_context'] ?? null,
            $metadata['quick_generate'] ?? null,
            $this->toArray($readModel->facts['cards']['campaign'] ?? [])['quick_generate_context'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $context = $this->toArray($candidate);

            if ($context !== []) {
                return $context;
            }
        }

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function campaignRecipientQuickGenerateLinks(CockpitReadModelQueryData $query, CockpitCampaignReadModelData $readModel): array
    {
        $recipientContexts = $this->campaignAdapterRecipientSourceContexts($readModel);

        if ($recipientContexts === []) {
            return [];
        }

        return collect($recipientContexts)
            ->take(10)
            ->map(function (array $context, int $index) use ($query): array {
                $link = $this->campaignQuickGenerateLink($query, $context);
                $label = $this->nullableString($context['label'] ?? null)
                    ?? $this->nullableString($context['name'] ?? null)
                    ?? $this->nullableString($context['recipient_name'] ?? null)
                    ?? 'Open Quick Generate';

                return array_merge($link, [
                    'key' => $this->nullableString($context['key'] ?? null)
                        ?? $this->nullableString($context['recipient_id'] ?? null)
                        ?? 'recipient-'.($index + 1),
                    'label' => $label,
                    'redactions' => [
                        'payloads' => 'campaign-recipient-source-link-query-only',
                        'excluded' => [
                            'campaign_payload',
                            'recipient_payload',
                            'provider_payload',
                            'wallet',
                            'balance',
                            'raw_payload',
                            'mobile',
                            'mobile_number',
                            'email',
                            'email_address',
                            'pay_code_generation_payload',
                            'delivery_dispatch_payload',
                        ],
                    ],
                ]);
            })
            ->filter(fn (array $link): bool => ($link['enabled'] ?? false) === true)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function campaignAdapterRecipientSourceContexts(CockpitCampaignReadModelData $readModel): array
    {
        $metadata = $this->toArray($readModel->facts['metadata'] ?? []);
        $campaignCard = $this->toArray($readModel->facts['cards']['campaign'] ?? []);
        $candidates = [
            $metadata['recipient_quick_generate_contexts'] ?? null,
            $metadata['quick_generate_recipients'] ?? null,
            $metadata['recipients'] ?? null,
            $campaignCard['recipient_quick_generate_contexts'] ?? null,
            $campaignCard['quick_generate_recipients'] ?? null,
            $campaignCard['recipients'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $contexts = $this->arrayListOfArrays($candidate);

            if ($contexts !== []) {
                return $contexts;
            }
        }

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function arrayListOfArrays(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn (mixed $item): array => $this->toArray($item))
            ->filter(fn (array $item): bool => $item !== [])
            ->values()
            ->all();
    }

    /**
     * @return array{template_key?: mixed, amount?: mixed, currency?: mixed, recipient_reference?: mixed, purpose?: mixed}
     */
    private function campaignSourceLinkDraft(array $adapterContext): array
    {
        if ($adapterContext === []) {
            return [];
        }

        $draft = ($this->campaignDraftAdapter ?? new DefaultCockpitCampaignIssuanceDraftAdapter)
            ->fromCampaignContext($adapterContext);

        return [
            'template_key' => $draft->template_key,
            'amount' => $draft->amount,
            'currency' => $draft->currency,
            'recipient_reference' => $draft->recipient_reference,
            'purpose' => $draft->purpose ?? $this->nullableString($draft->rider['message'] ?? null),
        ];
    }

    public function forOperatorIssuanceActivity(CockpitReadModelQueryData $query): CockpitOperatorIssuanceActivityReadModelData
    {
        return $this->operatorIssuanceActivity?->forOperator($query)
            ?? $this->fallback->forOperatorIssuanceActivity($query);
    }

    public function forPayCodeList(CockpitReadModelQueryData $query): CockpitPayCodeListReadModelData
    {
        $queryCode = $this->normalizeCode($query->code);
        $search = $this->normalizeSearch($query->payCodeSearch ?? $query->code);
        $statusFilter = $this->normalizeStatusFilter($query->payCodeStatus);
        $sourceRows = collect($this->vouchers->list($this->payCodeListFilters($query)))
            ->map(fn (mixed $row): array => $this->toArray($row))
            ->filter(fn (array $row): bool => $this->summaryCode($row, '') !== '')
            ->values();
        $filteredRows = $sourceRows
            ->when($search !== null, fn ($rows) => $rows->filter(
                fn (array $row): bool => $this->matchesPayCodeSearch($row, $search)
            ))
            ->when($statusFilter !== null, fn ($rows) => $rows->filter(
                fn (array $row): bool => $this->matchesOperationalStatusFilter($row, $statusFilter)
            ))
            ->values();
        $voucherIds = $filteredRows
            ->map(fn (array $row): mixed => $row['voucher_id'] ?? $row['id'] ?? null)
            ->filter(fn (mixed $id): bool => is_numeric($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
        $vouchers = Voucher::query()
            ->with('owner')
            ->whereKey($voucherIds)
            ->get()
            ->keyBy(fn (Voucher $voucher): string => (string) $voucher->getKey());
        $terminalControls = ($this->terminalControls ?? new PayCodeTerminalControlReadModel)
            ->forVouchers($vouchers, $query->actor);
        $rows = $filteredRows
            ->map(function (array $row) use ($terminalControls): ?CockpitPayCodeListRecordData {
                $voucherId = (string) ($row['voucher_id'] ?? $row['id'] ?? '');

                return $this->listRecord(
                    row: $row,
                    terminalControl: is_array($terminalControls[$voucherId] ?? null)
                        ? $terminalControls[$voucherId]
                        : [],
                );
            })
            ->filter()
            ->values()
            ->all();

        return new CockpitPayCodeListReadModelData(
            status: 'available',
            authorized: true,
            query: $search ?? $queryCode,
            status_filter: $statusFilter,
            stats: $this->payCodeStats($sourceRows, $filteredRows->count()),
            filters: $this->payCodeFilters($search, $statusFilter),
            records: $rows,
            redactions: [
                'payloads' => 'sanitized-list-summary-only',
                'instructions' => 'allowlisted-operational-badges-only',
                'party' => 'masked-contact-summary-only',
                'excluded' => $this->excludedPayloadKeys(),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payCodeListFilters(CockpitReadModelQueryData $query): array
    {
        $filters = [
            'include' => ['redeemer'],
        ];

        if (
            ! $query->canViewAllPayCodes
            && $query->operatorId !== null
            && $query->operatorType !== null
        ) {
            $filters['issuer_id'] = $query->operatorId;
            $filters['issuer_type'] = $query->operatorType;
        }

        return $filters;
    }

    private function normalizeSearch(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtoupper(trim($value));

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeStatusFilter(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim($value));

        $normalized = match ($normalized) {
            'redeemed', 'closed' => 'completed',
            'pending' => 'processing',
            'failed' => 'needs_attention',
            'locked' => 'active',
            default => $normalized,
        };

        return in_array($normalized, $this->payCodeStatusKeys(), true) && $normalized !== 'all'
            ? $normalized
            : null;
    }

    private function normalizeCode(?string $code): ?string
    {
        $normalized = strtoupper(trim((string) $code));

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(mixed $detail): array
    {
        if (is_array($detail)) {
            return $detail;
        }

        if ($detail instanceof Arrayable) {
            return $detail->toArray();
        }

        if ($detail instanceof JsonSerializable) {
            $serialized = $detail->jsonSerialize();

            return is_array($serialized) ? $serialized : [];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>
     */
    private function summary(array $detail, string $fallbackCode): array
    {
        return collect([
            'code' => $this->summaryCode($detail, $fallbackCode),
            'status' => $this->summaryStatus($detail),
            'display_status' => $detail['display_status'] ?? null,
            'amount' => $detail['amount'] ?? null,
            'currency' => $detail['currency'] ?? null,
            'claimed' => $detail['claimed'] ?? null,
            'fully_claimed' => $detail['fully_claimed'] ?? null,
            'created_at' => $detail['created_at'] ?? null,
            'starts_at' => $detail['starts_at'] ?? null,
            'expires_at' => $detail['expires_at'] ?? null,
            'redeemed_at' => $detail['redeemed_at'] ?? null,
            'redemption' => $detail['redemption'] ?? null,
            'voucher_status' => $detail['voucher_status'] ?? null,
            'operational_status' => $detail['operational_status'] ?? null,
            'attention' => $detail['attention'] ?? null,
            'external_reference' => $detail['external_reference'] ?? null,
            'consumer_status' => $detail['consumer_status'] ?? null,
        ])
            ->filter(fn (mixed $value, string $key): bool => $key === 'redeemed_at' || $value !== null)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function listRecord(
        array $row,
        array $terminalControl = [],
    ): ?CockpitPayCodeListRecordData {
        $code = $this->summaryCode($row, '');

        if ($code === '') {
            return null;
        }

        $status = $this->legacyIndexStatus($row);

        return new CockpitPayCodeListRecordData(
            code: $code,
            template: $this->stringValue($row['template'] ?? null, 'Pay Code'),
            capability: $this->payCodeCapability($row),
            instruction_badges: $this->payCodeInstructionBadges($row),
            amount: $this->amountValue($row['formatted_amount'] ?? $row['amount'] ?? null),
            currency: $this->nullableString($row['currency'] ?? null),
            status: $status,
            display_status: $this->stringValue($row['display_status'] ?? null, $status),
            purpose: $this->nullableString(
                $row['purpose'] ?? data_get($row, 'instructions.rider.message'),
            ),
            party: $this->payCodeParty($row, $status),
            timing: $this->payCodeTiming($row, $terminalControl),
            terminal_control: new CockpitPayCodeTerminalControlData(
                can_expire: ($terminalControl['can_expire'] ?? false) === true,
                can_cancel: ($terminalControl['can_cancel'] ?? false) === true,
                blocked_reason: $this->nullableString($terminalControl['blocked_reason'] ?? null),
                status: $this->stringValue($terminalControl['status'] ?? null, 'blocked'),
            ),
            owner: $this->stringValue($row['owner'] ?? null, 'Redacted'),
            last_activity: $this->nullableString(
                $row['last_activity']
                    ?? $row['updated_at']
                    ?? $row['redeemed_at']
                    ?? $row['created_at']
                    ?? null
            ),
            attention: $this->payCodeAttention($row),
            actions: $this->payCodeRowActions($code, $this->canDistributePayCode($row, $status)),
            consumer_status: $this->nullableString($row['consumer_status'] ?? null),
            collection: is_array($row['collection'] ?? null) ? $row['collection'] : [],
        );
    }

    private function payCodeAttention(array $row): ?CockpitPayCodeAttentionData
    {
        $attention = is_array($row['attention'] ?? null) ? $row['attention'] : [];
        $key = $this->nullableString($attention['key'] ?? null);
        $label = $this->nullableString($attention['label'] ?? null);
        $message = $this->nullableString($attention['message'] ?? null);

        if ($key === null || $label === null || $message === null) {
            return null;
        }

        return new CockpitPayCodeAttentionData(
            key: $key,
            label: $label,
            message: $message,
            tone: $this->stringValue($attention['tone'] ?? null, 'critical'),
        );
    }

    private function payCodeCapability(array $row): CockpitPayCodeCapabilityData
    {
        $capability = is_array($row['capability'] ?? null) ? $row['capability'] : [];

        return new CockpitPayCodeCapabilityData(
            key: $this->stringValue($capability['key'] ?? null, 'disbursement'),
            label: $this->stringValue($capability['label'] ?? null, 'Disbursement'),
            voucher_type_label: $this->stringValue(
                $capability['voucher_type_label'] ?? null,
                'Redeemable',
            ),
        );
    }

    /**
     * @return array<int, CockpitPayCodeInstructionBadgeData>
     */
    private function payCodeInstructionBadges(array $row): array
    {
        $badges = is_array($row['instruction_badges'] ?? null)
            ? $row['instruction_badges']
            : [];

        return collect($badges)
            ->filter(fn (mixed $badge): bool => is_array($badge))
            ->map(function (array $badge): ?CockpitPayCodeInstructionBadgeData {
                $key = $this->nullableString($badge['key'] ?? null);
                $label = $this->nullableString($badge['label'] ?? null);

                return $key !== null && $label !== null
                    ? new CockpitPayCodeInstructionBadgeData($key, $label)
                    : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function payCodeParty(array $row, string $status): CockpitPayCodePartyData
    {
        $party = is_array($row['party'] ?? null) ? $row['party'] : [];

        if ($party === [] && in_array($status, ['cancelled', 'canceled', 'closed', 'expired'], true)) {
            $label = Str::headline($status === 'canceled' ? 'cancelled' : $status);

            return new CockpitPayCodePartyData(
                state: $status,
                label: 'Availability',
                primary: $label,
                secondary: null,
                masked: false,
            );
        }

        return new CockpitPayCodePartyData(
            state: $this->stringValue($party['state'] ?? null, 'open'),
            label: $this->stringValue($party['label'] ?? null, 'Availability'),
            primary: $this->stringValue($party['primary'] ?? null, 'Open claim'),
            secondary: $this->nullableString($party['secondary'] ?? null),
            masked: ($party['masked'] ?? false) === true,
        );
    }

    private function payCodeTiming(array $row, array $terminalControl = []): CockpitPayCodeTimingData
    {
        $timing = is_array($row['timing'] ?? null) ? $row['timing'] : [];

        return new CockpitPayCodeTimingData(
            created_at: $this->nullableString($timing['created_at'] ?? $row['created_at'] ?? null),
            starts_at: $this->nullableString($timing['starts_at'] ?? $row['starts_at'] ?? null),
            expires_at: $this->nullableString($timing['expires_at'] ?? $row['expires_at'] ?? null),
            redeemed_at: $this->nullableString($timing['redeemed_at'] ?? $row['redeemed_at'] ?? null),
            terminal_at: collect($terminalControl['history'] ?? [])
                ->filter(fn (mixed $event): bool => is_array($event))
                ->map(fn (array $event): ?string => $this->nullableString($event['occurred_at'] ?? null))
                ->filter()
                ->sort()
                ->last(),
        );
    }

    /**
     * @return array<int, CockpitPayCodeRowActionData>
     */
    private function payCodeRowActions(string $code, bool $canDistribute): array
    {
        $encodedCode = rawurlencode($code);

        return [
            new CockpitPayCodeRowActionData(
                key: 'detail',
                label: 'View details',
                enabled: true,
                read_only: true,
                href: "/x/cockpit/pay-codes/{$encodedCode}",
                reason: 'Read-only Cockpit voucher detail route.',
            ),
            new CockpitPayCodeRowActionData(
                key: 'distribution',
                label: 'Distribution',
                enabled: $canDistribute,
                read_only: true,
                href: $canDistribute ? "/x/cockpit/pay-codes/{$encodedCode}/distribution" : null,
                reason: $canDistribute
                    ? 'Read-only Cockpit distribution workspace route.'
                    : 'Terminal Pay Codes cannot be distributed for claim.',
            ),
            new CockpitPayCodeRowActionData(
                key: 'timeline',
                label: 'Open timeline',
                enabled: false,
                read_only: true,
                reason: 'Timeline requires journal visibility and redaction wiring.',
            ),
            new CockpitPayCodeRowActionData(
                key: 'notify',
                label: 'Notify recipient',
                enabled: false,
                read_only: true,
                reason: 'Feedback delivery remains separately gated through x-feedback.',
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function canDistributePayCode(array $row, string $status): bool
    {
        $canClaim = data_get($row, 'operational_status.can_claim');

        if (is_bool($canClaim)) {
            return $canClaim;
        }

        return ! in_array($status, [
            'cancelled',
            'canceled',
            'closed',
            'expired',
            'paid',
            'redeemed',
            'payout_pending',
            'payout_rejected',
        ], true);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function matchesPayCodeSearch(array $row, string $search): bool
    {
        $fields = [
            $this->summaryCode($row, ''),
            $row['mobile'] ?? null,
            $row['account_number'] ?? null,
            $row['bank_code'] ?? null,
            $row['status'] ?? null,
            $row['display_status'] ?? null,
            $row['formatted_amount'] ?? null,
            $row['amount'] ?? null,
            $row['template'] ?? null,
            data_get($row, 'capability.label'),
            data_get($row, 'capability.voucher_type_label'),
            data_get($row, 'party.primary'),
            data_get($row, 'party.secondary'),
            data_get($row, 'operational_status.label'),
            data_get($row, 'operational_status.availability_label'),
            data_get($row, 'operational_status.settlement_outcome'),
            $row['purpose'] ?? null,
            data_get($row, 'instructions.metadata.custom.external_reference'),
            data_get($row, 'instructions.rider.message'),
        ];

        foreach (($row['instruction_badges'] ?? []) as $badge) {
            if (is_array($badge)) {
                $fields[] = $badge['label'] ?? null;
            }
        }

        return collect($fields)
            ->filter(fn (mixed $value): bool => is_scalar($value) && trim((string) $value) !== '')
            ->map(fn (mixed $value): string => strtoupper(trim((string) $value)))
            ->contains(fn (string $value): bool => str_contains($value, $search));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function legacyIndexStatus(array $row): string
    {
        if (isset($row['display_status']) && is_scalar($row['display_status']) && trim((string) $row['display_status']) !== '') {
            return strtolower(trim((string) $row['display_status']));
        }

        $approval = $row['approval'] ?? null;

        if (is_array($approval) && ($approval['required'] ?? false) === true) {
            return 'awaiting_approval';
        }

        if (isset($row['status']) && is_scalar($row['status']) && trim((string) $row['status']) !== '') {
            return strtolower(trim((string) $row['status']));
        }

        if (isset($row['redeemed_at']) && is_scalar($row['redeemed_at']) && trim((string) $row['redeemed_at']) !== '') {
            return 'redeemed';
        }

        if ($this->isExpired($row['expires_at'] ?? null)) {
            return 'expired';
        }

        return 'active';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function matchesOperationalStatusFilter(array $row, string $filter): bool
    {
        $status = $this->legacyIndexStatus($row);

        return match ($filter) {
            'active' => in_array($status, ['active', 'issued', 'ready', 'locked', 'scheduled', 'partially_claimed'], true),
            'awaiting_approval' => $status === 'awaiting_approval',
            'processing' => in_array($status, ['payout_pending', 'pending', 'processing'], true),
            'completed' => in_array($status, ['paid', 'redeemed', 'completed', 'closed'], true),
            'needs_attention' => $status === 'payout_rejected'
                || $this->nullableString(data_get($row, 'attention.key')) !== null,
            'expired' => $status === 'expired',
            'cancelled' => in_array($status, ['cancelled', 'canceled'], true),
            default => $status === $filter,
        };
    }

    private function isExpired(mixed $value): bool
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return false;
        }

        $timestamp = strtotime((string) $value);

        return $timestamp !== false && $timestamp < time();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function payCodeStats($rows, int $filtered): CockpitPayCodeExplorerStatsData
    {
        return new CockpitPayCodeExplorerStatsData(
            total: $rows->count(),
            active: $this->countOperationalStatus($rows, 'active'),
            awaiting_approval: $this->countLegacyStatus($rows, 'awaiting_approval'),
            redeemed: $this->countOperationalStatus($rows, 'completed'),
            expired: $this->countLegacyStatus($rows, 'expired'),
            pending: $this->countOperationalStatus($rows, 'processing'),
            failed: $this->countOperationalStatus($rows, 'needs_attention'),
            filtered: $filtered,
        );
    }

    /**
     * @return array<int, CockpitPayCodeExplorerFilterData>
     */
    private function payCodeFilters(?string $search, ?string $statusFilter): array
    {
        return collect($this->payCodeStatusOptions())
            ->map(fn (array $option): CockpitPayCodeExplorerFilterData => new CockpitPayCodeExplorerFilterData(
                key: 'status',
                label: $option['label'],
                value: $option['value'],
                active: $statusFilter === $option['value'] || ($statusFilter === null && $option['value'] === 'all'),
            ))
            ->prepend(new CockpitPayCodeExplorerFilterData(
                key: 'search',
                label: 'Search',
                value: $search ?? '',
                active: $search !== null,
            ))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function payCodeStatusOptions(): array
    {
        return [
            ['value' => 'all', 'label' => 'All'],
            ['value' => 'active', 'label' => 'Active'],
            ['value' => 'awaiting_approval', 'label' => 'Awaiting Approval'],
            ['value' => 'processing', 'label' => 'Processing'],
            ['value' => 'completed', 'label' => 'Completed'],
            ['value' => 'needs_attention', 'label' => 'Needs Attention'],
            ['value' => 'expired', 'label' => 'Expired'],
            ['value' => 'cancelled', 'label' => 'Cancelled'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function payCodeStatusKeys(): array
    {
        return array_column($this->payCodeStatusOptions(), 'value');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function countLegacyStatus($rows, string $status): int
    {
        return $rows
            ->filter(fn (array $row): bool => $this->legacyIndexStatus($row) === $status)
            ->count();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function countOperationalStatus($rows, string $status): int
    {
        return $rows
            ->filter(fn (array $row): bool => $this->matchesOperationalStatusFilter($row, $status))
            ->count();
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function summaryCode(array $detail, string $fallbackCode): string
    {
        $code = $detail['code'] ?? null;

        if (is_scalar($code) && trim((string) $code) !== '') {
            return strtoupper(trim((string) $code));
        }

        return $fallbackCode;
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function summaryStatus(array $detail): string
    {
        $status = $detail['status'] ?? null;

        if (is_scalar($status) && trim((string) $status) !== '') {
            return trim((string) $status);
        }

        return 'available';
    }

    private function stringValue(mixed $value, string $fallback): string
    {
        if (is_scalar($value) && trim((string) $value) !== '') {
            return trim((string) $value);
        }

        return $fallback;
    }

    private function nullableString(mixed $value): ?string
    {
        if (is_scalar($value) && trim((string) $value) !== '') {
            return trim((string) $value);
        }

        return null;
    }

    private function amountValue(mixed $value): string|int|float|null
    {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        return null;
    }

    /**
     * @return array<int, CockpitDashboardMetricData>
     */
    private function liabilityMetrics(CockpitReadModelQueryData $query): array
    {
        if ($this->liabilities === null || $query->operatorId === null) {
            return [];
        }

        $operator = $this->resolveOperator($query->operatorId);

        if ($operator === null) {
            return [];
        }

        $summary = $this->liabilities->forIssuer($operator);

        return [
            new CockpitDashboardMetricData(
                key: 'active-issued-liability',
                label: 'Active Issued',
                value: $this->formatMinorMoney($summary->active_issued_minor, $summary->currency),
                helper: $summary->active_count.' active Pay Codes counted as outstanding liability.',
                tone: $summary->active_issued_minor > 0 ? 'warning' : 'healthy',
            ),
            new CockpitDashboardMetricData(
                key: 'redeemed-liability',
                label: 'Redeemed Amount',
                value: $this->formatMinorMoney($summary->redeemed_minor, $summary->currency),
                helper: $summary->redeemed_count.' redeemed Pay Codes excluded from outstanding liability.',
                tone: 'healthy',
            ),
            new CockpitDashboardMetricData(
                key: 'expired-liability',
                label: 'Expired Amount',
                value: $this->formatMinorMoney($summary->expired_minor, $summary->currency),
                helper: $summary->expired_count.' expired Pay Codes excluded from outstanding liability; no wallet release is performed.',
                tone: $summary->expired_minor > 0 ? 'warning' : 'neutral',
            ),
            new CockpitDashboardMetricData(
                key: 'cancelled-liability',
                label: 'Cancelled Amount',
                value: $this->formatMinorMoney($summary->cancelled_minor, $summary->currency),
                helper: $summary->cancelled_count.' cancelled Pay Codes excluded from outstanding liability; eligible unclaimed Treasury reserves return to Client Funds exactly once.',
                tone: $summary->cancelled_minor > 0 ? 'warning' : 'neutral',
            ),
            ...$this->moneyMovementDecisionMetrics(),
        ];
    }

    /**
     * @return array<int, CockpitDashboardMetricData>
     */
    private function moneyMovementDecisionMetrics(): array
    {
        if ($this->moneyMovementDecision === null) {
            return [];
        }

        $decision = $this->moneyMovementDecision->current();

        return [
            new CockpitDashboardMetricData(
                key: 'money-movement-model',
                label: 'Money Movement Model',
                value: str($decision->current_model)->replace('_', ' ')->title()->toString(),
                helper: 'Reservation/release accounting is still a decision point; Cockpit shows read-only estimates only.',
                tone: 'warning',
            ),
        ];
    }

    private function resolveOperator(string $operatorId): ?Model
    {
        $class = (string) config('x-change.lifecycle.defaults.user_model', config('auth.providers.users.model'));

        if ($class === '' || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        /** @var Model|null $operator */
        $operator = $class::query()->find($operatorId);

        return $operator;
    }

    private function formatMinorMoney(int $amount, string $currency): string
    {
        return $currency.' '.number_format($amount / 100, 2);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function countStatus($rows, string $status): int
    {
        return $rows
            ->filter(fn (array $row): bool => $this->summaryStatus($row) === $status)
            ->count();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, CockpitDashboardActivityData>
     */
    private function dashboardActivity($rows, CockpitReadModelQueryData $query): array
    {
        $voucherActivity = $rows
            ->map(fn (array $row): array => [
                'code' => $this->summaryCode($row, ''),
                'display_status' => $this->stringValue($row['display_status'] ?? null, $this->summaryStatus($row)),
                'timestamp' => $this->nullableString(
                    $row['updated_at']
                        ?? $row['redeemed_at']
                        ?? $row['expires_at']
                        ?? $row['created_at']
                        ?? null
                ),
            ])
            ->filter(fn (array $row): bool => $row['code'] !== '' && $row['timestamp'] !== null)
            ->sortByDesc('timestamp')
            ->take(3)
            ->map(fn (array $row): CockpitDashboardActivityData => new CockpitDashboardActivityData(
                id: $row['code'],
                label: $row['code'],
                description: 'Status: '.$row['display_status'],
                timestamp: $row['timestamp'],
                source: 'system',
            ))
            ->values()
            ->all();

        return collect([
            ...($this->integrations?->executionActivities($query) ?? []),
            ...$voucherActivity,
        ])
            ->sortByDesc(fn (CockpitDashboardActivityData $activity): string => $activity->timestamp)
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function excludedPayloadKeys(): array
    {
        return [
            'id',
            'voucher_id',
            'issuer_id',
            'instructions',
            'claims',
            'approval',
            'provider_payload',
            'raw_payload',
            'wallet',
            'provider',
        ];
    }
}
